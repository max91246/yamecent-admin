<?php

namespace App\Http\Controllers\Api;

use App\Member;
use App\MemberBalanceLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Storage;

class MemberController extends Controller
{
    /**
     * POST /api/members/register
     */
    public function register(Request $request)
    {
        $data = $request->only(['account', 'password', 'nickname', 'email', 'phone']);

        if (empty($data['account'])) {
            return response()->json(['code' => 422, 'msg' => '帳號為必填', 'data' => null]);
        }
        if (empty($data['password'])) {
            return response()->json(['code' => 422, 'msg' => '密碼為必填', 'data' => null]);
        }
        if (empty($data['nickname'])) {
            return response()->json(['code' => 422, 'msg' => '暱稱為必填', 'data' => null]);
        }
        if (Member::isExist($data['account'])) {
            return response()->json(['code' => 409, 'msg' => '帳號已存在', 'data' => null]);
        }

        $file = $request->file('avatar');
        if ($file && $file->isValid()) {
            $ext = $file->getClientOriginalExtension();
            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif'])) {
                return response()->json(['code' => 422, 'msg' => '頭像格式不正確，僅支援 jpg/png/gif', 'data' => null]);
            }
            if ($file->getSize() > 5000000) {
                return response()->json(['code' => 422, 'msg' => '頭像不能大於 5MB', 'data' => null]);
            }
            $filename = 'avatar/' . date('Ymd') . '/' . uniqid() . '.' . $ext;
            Storage::disk('admin')->put($filename, file_get_contents($file->getRealPath()));
            $data['avatar'] = '/uploads/' . $filename;
        }

        $member = new Member();
        $member->fill($data);
        $member->save();

        $result = $member->only(['id', 'account', 'avatar', 'nickname', 'email', 'phone', 'is_active', 'created_at']);

        return response()->json(['code' => 200, 'msg' => 'success', 'data' => $result]);
    }

    /**
     * POST /api/members/{id}/profile
     */
    public function updateProfile(Request $request, $id)
    {
        // 只能更新自己的資料
        if ((int) $request->attributes->get('auth_member_id') !== (int) $id) {
            return response()->json(['code' => 403, 'msg' => '無權限修改他人資料', 'data' => null]);
        }

        $member = Member::find($id);
        if (!$member) {
            return response()->json(['code' => 404, 'msg' => '會員不存在', 'data' => null]);
        }

        // 暱稱
        $nickname = $request->input('nickname');
        if (!is_null($nickname)) {
            if (trim($nickname) === '') {
                return response()->json(['code' => 422, 'msg' => '暱稱不能為空', 'data' => null]);
            }
            $member->nickname = trim($nickname);
        }

        // Email（允許清空）
        if ($request->has('email')) {
            $member->email = $request->input('email') ?: null;
        }

        // 手機（允許清空）
        if ($request->has('phone')) {
            $member->phone = $request->input('phone') ?: null;
        }

        // 密碼（僅在有值時更新）
        $password = $request->input('password');
        if (!is_null($password) && $password !== '') {
            if (strlen($password) < 6) {
                return response()->json(['code' => 422, 'msg' => '密碼至少需要 6 位', 'data' => null]);
            }
            $member->password = $password; // setPasswordAttribute 自動 hash
        }

        // 頭像
        $file = $request->file('avatar');
        if ($file && $file->isValid()) {
            $ext = strtolower($file->getClientOriginalExtension());
            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif'])) {
                return response()->json(['code' => 422, 'msg' => '頭像格式不正確，僅支援 jpg/png/gif', 'data' => null]);
            }
            if ($file->getSize() > 5000000) {
                return response()->json(['code' => 422, 'msg' => '頭像不能大於 5MB', 'data' => null]);
            }
            $filename = 'avatar/' . date('Ymd') . '/' . uniqid() . '.' . $ext;
            Storage::disk('admin')->put($filename, file_get_contents($file->getRealPath()));
            $member->avatar = '/uploads/' . $filename;
        }

        $member->save();

        $result = $member->only(['id', 'account', 'avatar', 'nickname', 'email', 'phone', 'is_active']);

        return response()->json(['code' => 200, 'msg' => '更新成功', 'data' => $result]);
    }

    /**
     * GET /api/members/{id}
     */
    public function show($id)
    {
        $member = Member::find($id);

        if (!$member) {
            return response()->json(['code' => 404, 'msg' => '會員不存在', 'data' => null]);
        }

        $result = array_merge(
            $member->only(['id', 'account', 'avatar', 'nickname', 'email', 'phone', 'is_active', 'created_at']),
            [
                'is_member'          => (int) $member->is_member,
                'member_expired_at'  => $member->member_expired_at  ? (string) $member->member_expired_at  : null,
                'member_applied_at'  => $member->member_applied_at  ? (string) $member->member_applied_at  : null,
                'is_member_active'   => $member->isMemberActive(),
            ]
        );

        return response()->json(['code' => 200, 'msg' => 'success', 'data' => $result]);
    }

    /**
     * POST /api/members/{id}/membership/apply
     */
    public function applyMembership(Request $request, $id)
    {
        if ((int) $request->attributes->get('auth_member_id') !== (int) $id) {
            return response()->json(['code' => 403, 'msg' => '無權限', 'data' => null]);
        }

        $member = Member::find($id);
        if (!$member) {
            return response()->json(['code' => 404, 'msg' => '會員不存在', 'data' => null]);
        }

        if ($member->isMemberActive()) {
            return response()->json(['code' => 200, 'msg' => 'already_member', 'data' => null]);
        }

        if ($member->member_applied_at && !$member->is_member) {
            return response()->json(['code' => 200, 'msg' => 'pending', 'data' => null]);
        }

        $member->update(['member_applied_at' => now()]);

        return response()->json(['code' => 200, 'msg' => 'applied', 'data' => null]);
    }

    /**
     * GET /api/members/{id}/transactions
     */
    public function transactions($id)
    {
        if (!Member::find($id)) {
            return response()->json(['code' => 404, 'msg' => '會員不存在', 'data' => null]);
        }

        $paginator = MemberBalanceLog::where('member_id', $id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $logs = collect($paginator->items())->map(function ($log) {
            return [
                'id'             => $log->id,
                'type'           => $log->type,
                'amount'         => $log->amount,
                'before_balance' => $log->before_balance,
                'after_balance'  => $log->after_balance,
                'remark'         => $log->remark,
                'created_at'     => (string) $log->created_at,
            ];
        });

        return response()->json([
            'code' => 200,
            'msg'  => 'success',
            'data' => $logs,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }
}
