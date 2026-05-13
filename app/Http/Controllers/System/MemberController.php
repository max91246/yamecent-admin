<?php

namespace App\Http\Controllers\System;

use App\Member;
use App\MemberBalanceLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberController extends Controller
{
    // ── 會員列表 ──────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = Member::query();

        if ($keyword = $request->input('keyword')) {
            $query->where(function ($q) use ($keyword) {
                $q->where('nickname', 'like', "%{$keyword}%")
                  ->orWhere('account', 'like', "%{$keyword}%")
                  ->orWhere('email',   'like', "%{$keyword}%")
                  ->orWhere('phone',   'like', "%{$keyword}%");
            });
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->input('is_active'));
        }
        if ($request->filled('is_member')) {
            $query->where('is_member', $request->input('is_member'));
        }

        $pageSize    = (int)$request->input('pageSize', 10);
        $currentPage = (int)$request->input('currentPage', 1);
        $paginator   = $query->orderBy('id', 'desc')->paginate($pageSize, ['*'], 'page', $currentPage);

        return response()->json([
            'success' => true,
            'data'    => [
                'list'        => collect($paginator->items())->map(fn($m) => $this->format($m)),
                'total'       => $paginator->total(),
                'pageSize'    => $pageSize,
                'currentPage' => $currentPage,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->only(['account', 'nickname', 'password', 'email', 'phone', 'is_active']);
        $member = Member::create($data);
        return response()->json(['success' => true, 'data' => $this->format($member)]);
    }

    public function update(Request $request, $id)
    {
        $member = Member::findOrFail($id);
        $data   = $request->only(['nickname', 'email', 'phone', 'is_active', 'can_comment']);
        if ($pwd = $request->input('password')) {
            $data['password'] = $pwd;
        }
        $member->update($data);
        return response()->json(['success' => true, 'data' => $this->format($member->fresh())]);
    }

    public function destroy($id)
    {
        Member::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    /** 啟用/延長會員資格 */
    public function activateMembership(Request $request, $id)
    {
        $days   = max(1, (int)$request->input('days', 30));
        $member = Member::findOrFail($id);
        $base   = ($member->is_member && $member->member_expired_at) ? $member->member_expired_at : now();
        $member->update(['is_member' => 1, 'member_expired_at' => $base->addDays($days)]);
        return response()->json(['success' => true, 'memberExpiredAt' => $member->fresh()->member_expired_at->format('Y-m-d H:i:s')]);
    }

    /** 撤銷會員資格 */
    public function revokeMembership($id)
    {
        Member::findOrFail($id)->update(['is_member' => 0, 'member_expired_at' => null]);
        return response()->json(['success' => true]);
    }

    // ── 餘額記錄 ──────────────────────────────────────────────

    public function balanceLogs(Request $request)
    {
        $query = MemberBalanceLog::with('member:id,account,nickname')->orderBy('id', 'desc');
        if ($memberId = $request->input('member_id')) {
            $query->where('member_id', $memberId);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $pageSize    = (int)$request->input('pageSize', 15);
        $currentPage = (int)$request->input('currentPage', 1);
        $paginator   = $query->paginate($pageSize, ['*'], 'page', $currentPage);

        return response()->json([
            'success' => true,
            'data'    => [
                'list'        => collect($paginator->items())->map(fn($l) => $this->formatLog($l)),
                'total'       => $paginator->total(),
                'pageSize'    => $pageSize,
                'currentPage' => $currentPage,
            ],
        ]);
    }

    /** 手動調整餘額 */
    public function balanceAdjust(Request $request)
    {
        $memberId = $request->input('member_id');
        $type     = (int)$request->input('type');
        $amount   = $request->input('amount');
        $remark   = $request->input('remark', '管理員手動調整');

        try {
            DB::transaction(function () use ($memberId, $type, $amount, $remark) {
                $member = Member::lockForUpdate()->findOrFail($memberId);
                $before = (string)$member->balance;
                if ($type === 2 && bccomp($before, (string)$amount, 2) < 0) {
                    throw new \Exception('餘額不足');
                }
                $after = $type === 1 ? bcadd($before, (string)$amount, 2) : bcsub($before, (string)$amount, 2);
                $member->update(['balance' => $after]);
                MemberBalanceLog::create([
                    'member_id'      => $memberId,
                    'amount'         => $amount,
                    'before_balance' => $before,
                    'after_balance'  => $after,
                    'type'           => $type,
                    'remark'         => $remark,
                ]);
            });
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'msg' => $e->getMessage()], 422);
        }
    }

    // ── format ────────────────────────────────────────────────

    private function format(Member $m): array
    {
        return [
            'id'              => $m->id,
            'account'         => $m->account,
            'nickname'        => $m->nickname,
            'avatar'          => $m->avatar,
            'email'           => $m->email,
            'phone'           => $m->phone,
            'balance'         => $m->balance,
            'isActive'        => $m->is_active,
            'isMember'        => $m->is_member,
            'memberExpiredAt' => $m->member_expired_at?->format('Y-m-d H:i:s'),
            'canComment'      => $m->can_comment,
            'createdAt'       => $m->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function formatLog(MemberBalanceLog $l): array
    {
        return [
            'id'            => $l->id,
            'memberId'      => $l->member_id,
            'memberAccount' => $l->member?->account,
            'amount'        => $l->amount,
            'beforeBalance' => $l->before_balance,
            'afterBalance'  => $l->after_balance,
            'type'          => $l->type,
            'typeLabel'     => $l->type === 1 ? '增加' : '減少',
            'remark'        => $l->remark,
            'createdAt'     => $l->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
