<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\SysMenu;
use App\SysRole;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /** 角色列表（分頁） */
    public function index(Request $request)
    {
        $query = SysRole::query();

        if ($name = $request->input('name')) {
            $query->where('name', 'like', "%{$name}%");
        }
        if ($code = $request->input('code')) {
            $query->where('code', 'like', "%{$code}%");
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
                'list'        => collect($paginator->items())->map(fn($r) => $this->format($r)),
                'total'       => $paginator->total(),
                'pageSize'    => $pageSize,
                'currentPage' => $currentPage,
            ],
        ]);
    }

    /** 新增角色 */
    public function store(Request $request)
    {
        $role = SysRole::create($request->only(['name', 'code', 'status', 'remark']));
        return response()->json(['success' => true, 'data' => $this->format($role)]);
    }

    /** 修改角色 */
    public function update(Request $request, $id)
    {
        $role = SysRole::findOrFail($id);
        $role->update($request->only(['name', 'code', 'status', 'remark']));
        return response()->json(['success' => true, 'data' => $this->format($role->fresh())]);
    }

    /** 刪除角色 */
    public function destroy($id)
    {
        $role = SysRole::findOrFail($id);
        $role->menus()->detach();
        $role->delete();
        return response()->json(['success' => true]);
    }

    /** 全部角色（給用戶分配用） */
    public function listAll()
    {
        $roles = SysRole::where('status', 1)->get(['id', 'name', 'code']);
        return response()->json(['success' => true, 'data' => $roles]);
    }

    /** 菜單樹（角色權限設定時使用） */
    public function menuTree()
    {
        $menus = SysMenu::orderBy('rank')->orderBy('id')->get()
            ->map(fn($m) => [
                'id'       => $m->id,
                'parentId' => $m->parent_id,
                'title'    => $m->title,
                'menuType' => $m->menu_type,
                'icon'     => $m->icon,
                'path'     => $m->path,
                'name'     => $m->name,
                'auths'    => $m->auths,
                'showLink' => $m->show_link,
            ]);

        return response()->json(['success' => true, 'data' => $menus]);
    }

    /** 取得角色已分配的菜單 ID 列表 */
    public function menuIds(Request $request)
    {
        $role = SysRole::findOrFail($request->input('id'));
        $ids  = $role->menus()->pluck('sys_menus.id');
        return response()->json(['success' => true, 'data' => $ids]);
    }

    /** 儲存角色菜單權限 */
    public function saveMenus(Request $request)
    {
        $role    = SysRole::findOrFail($request->input('id'));
        $menuIds = $request->input('menuIds', []);
        $role->menus()->sync($menuIds);
        return response()->json(['success' => true]);
    }

    private function format(SysRole $r): array
    {
        return [
            'id'         => $r->id,
            'name'       => $r->name,
            'code'       => $r->code,
            'status'     => $r->status,
            'remark'     => $r->remark,
            'createTime' => $r->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
