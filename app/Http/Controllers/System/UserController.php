<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\SysUser;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /** 用戶列表（分頁） */
    public function index(Request $request)
    {
        $query = SysUser::query();

        if ($username = $request->input('username')) {
            $query->where('username', 'like', "%{$username}%");
        }
        if ($phone = $request->input('phone')) {
            $query->where('phone', 'like', "%{$phone}%");
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $pageSize    = (int)$request->input('pageSize', 10);
        $currentPage = (int)$request->input('currentPage', 1);
        $paginator   = $query->orderBy('id')->paginate($pageSize, ['*'], 'page', $currentPage);

        return response()->json([
            'success' => true,
            'data'    => [
                'list'        => collect($paginator->items())->map(fn($u) => $this->format($u)),
                'total'       => $paginator->total(),
                'pageSize'    => $pageSize,
                'currentPage' => $currentPage,
            ],
        ]);
    }

    /** 新增用戶 */
    public function store(Request $request)
    {
        $user = SysUser::create($request->only([
            'username', 'password', 'nickname', 'avatar',
            'sex', 'phone', 'email', 'status', 'remark',
        ]));

        if ($roleIds = $request->input('roleIds')) {
            $user->roles()->sync($roleIds);
        }

        return response()->json(['success' => true, 'data' => $this->format($user->fresh())]);
    }

    /** 修改用戶 */
    public function update(Request $request, $id)
    {
        $user = SysUser::findOrFail($id);

        $fields = $request->only([
            'nickname', 'avatar', 'sex', 'phone', 'email', 'status', 'remark',
        ]);
        // 只有傳密碼才更新（走 setter 自動 hash）
        if ($pwd = $request->input('password')) {
            $fields['password'] = $pwd;
        }

        $user->update($fields);

        if ($request->has('roleIds')) {
            $user->roles()->sync($request->input('roleIds', []));
        }

        return response()->json(['success' => true, 'data' => $this->format($user->fresh())]);
    }

    /** 刪除用戶 */
    public function destroy($id)
    {
        $user = SysUser::findOrFail($id);
        $user->roles()->detach();
        $user->delete();
        return response()->json(['success' => true]);
    }

    /** 取得用戶已分配的角色 ID 列表 */
    public function roleIds(Request $request)
    {
        $user = SysUser::findOrFail($request->input('userId'));
        $ids  = $user->roles()->pluck('sys_roles.id');
        return response()->json(['success' => true, 'data' => $ids]);
    }

    private function format(SysUser $u): array
    {
        return [
            'id'         => $u->id,
            'avatar'     => $u->avatar,
            'username'   => $u->username,
            'nickname'   => $u->nickname,
            'sex'        => $u->sex,
            'phone'      => $u->phone,
            'email'      => $u->email,
            'status'     => $u->status,
            'remark'     => $u->remark,
            'createTime' => $u->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
