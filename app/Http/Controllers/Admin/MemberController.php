<?php

namespace App\Http\Controllers\Admin;

use App\Member;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function memberList(Request $request)
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

        if ($request->input('is_active') !== null && $request->input('is_active') !== '') {
            $query->where('is_active', $request->input('is_active'));
        }

        if (in_array($request->input('balance_sort'), ['asc', 'desc'])) {
            $query->orderBy('balance', $request->input('balance_sort'));
        } else {
            $query->orderBy('id', 'desc');
        }

        return view('admin.member_list', [
            'list' => $query->paginate(10)->appends($request->query()),
        ]);
    }

    public function memberAddView()
    {
        return view('admin.member_add');
    }

    public function memberAdd(Request $request)
    {
        $data = $request->only([
            'account', 'avatar', 'password', 'nickname',
            'email', 'phone', 'is_active',
        ]);

        if (empty($data['account'])) {
            return $this->json(500, '請填寫帳號');
        }
        if (empty($data['password'])) {
            return $this->json(500, '請填寫密碼');
        }
        if (empty($data['nickname'])) {
            return $this->json(500, '請填寫暱稱');
        }
        if (Member::isExist($data['account'])) {
            return $this->json(500, '此帳號已存在');
        }

        $data['is_active'] = isset($data['is_active']) ? 1 : 0;

        $member = new Member();
        $member->fill($data);
        $member->save();

        return $this->json(200, '新增成功');
    }

    public function memberUpdateView(Request $request, $id)
    {
        return view('admin.member_update', [
            'member' => Member::findOrFail($id),
        ]);
    }

    public function memberUpdate(Request $request, $id)
    {
        $data = $request->only([
            'account', 'avatar', 'password', 'nickname',
            'email', 'phone', 'is_active', 'can_comment',
        ]);

        if (empty($data['account'])) {
            return $this->json(500, '請填寫帳號');
        }
        if (empty($data['nickname'])) {
            return $this->json(500, '請填寫暱稱');
        }

        $member = Member::findOrFail($id);

        if ($member->isExistForUpdate($data['account'])) {
            return $this->json(500, '此帳號已存在');
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $data['is_active']   = isset($data['is_active'])   ? (int) $data['is_active']   : 0;
        $data['can_comment'] = isset($data['can_comment']) ? (int) $data['can_comment'] : 0;

        $member->fill($data)->save();

        return $this->json(200, '修改成功');
    }

    public function memberDel($id)
    {
        Member::findOrFail($id)->delete();
        return $this->json(200, '刪除成功');
    }

    public function memberSearch(Request $request)
    {
        $keyword = $request->input('keyword', '');
        $members = Member::where(function ($q) use ($keyword) {
                $q->where('account',  'like', "%{$keyword}%")
                  ->orWhere('nickname', 'like', "%{$keyword}%");
            })
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get(['id', 'account', 'nickname', 'balance']);

        return response()->json($members);
    }

    public function activateMembership(Request $request, $id)
    {
        $request->validate(['days' => 'required|integer|min:1|max:3650']);

        $member = Member::findOrFail($id);
        $days   = (int) $request->input('days');

        // 若已是有效會員則從到期時間延長，否則從現在起算
        $base = ($member->isMemberActive() && $member->member_expired_at)
            ? $member->member_expired_at
            : now();

        $member->update([
            'is_member'          => 1,
            'member_expired_at'  => $base->addDays($days),
        ]);

        return response()->json([
            'success'           => true,
            'member_expired_at' => $member->fresh()->member_expired_at->format('Y-m-d H:i:s'),
        ]);
    }

    public function revokeMembership($id)
    {
        Member::findOrFail($id)->update([
            'is_member'         => 0,
            'member_expired_at' => null,
        ]);

        return response()->json(['success' => true]);
    }
}
