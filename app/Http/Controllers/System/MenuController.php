<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\SysMenu;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    /** 菜單列表（含搜尋） */
    public function index(Request $request)
    {
        $query = SysMenu::query();

        if ($title = $request->input('title')) {
            $query->where('title', 'like', "%{$title}%");
        }

        $list = $query->orderBy('rank')->orderBy('id')->get()->map(fn($m) => $this->format($m));

        return response()->json(['success' => true, 'data' => $list]);
    }

    /** 新增菜單 */
    public function store(Request $request)
    {
        $menu = SysMenu::create($this->fields($request));
        return response()->json(['success' => true, 'data' => $this->format($menu)]);
    }

    /** 修改菜單 */
    public function update(Request $request, $id)
    {
        $menu = SysMenu::findOrFail($id);
        $menu->update($this->fields($request));
        return response()->json(['success' => true, 'data' => $this->format($menu->fresh())]);
    }

    /** 刪除菜單 */
    public function destroy($id)
    {
        SysMenu::destroy($id);
        return response()->json(['success' => true]);
    }

    /** 動態路由（/get-async-routes）：只回傳有 path 的菜單組成路由樹 */
    public function asyncRoutes()
    {
        $menus = SysMenu::where('show_link', true)
            ->where('menu_type', '<', 3)  // 排除按鈕型
            ->orderBy('rank')->orderBy('id')
            ->get();

        $tree = $this->buildTree($menus, 0);

        return response()->json(['success' => true, 'data' => $tree]);
    }

    // ─── private ───────────────────────────────────────────────

    private function buildTree($menus, $parentId)
    {
        $result = [];
        foreach ($menus as $menu) {
            if ((int)$menu->parent_id !== (int)$parentId) continue;

            $node = [
                'path'      => $menu->path,
                'name'      => $menu->name,
                'component' => $menu->component ?: null,
                'redirect'  => $menu->redirect  ?: null,
                'meta'      => [
                    'title'           => $menu->title,
                    'icon'            => $menu->icon,
                    'rank'            => $menu->rank,
                    'showLink'        => $menu->show_link,
                    'showParent'      => $menu->show_parent,
                    'keepAlive'       => $menu->keep_alive,
                    'frameSrc'        => $menu->frame_src      ?: null,
                    'frameLoading'    => $menu->frame_loading,
                    'hiddenTag'       => $menu->hidden_tag,
                    'fixedTag'        => $menu->fixed_tag,
                    'enterTransition' => $menu->enter_transition ?: null,
                    'leaveTransition' => $menu->leave_transition ?: null,
                    'activePath'      => $menu->active_path      ?: null,
                    'backstage'       => true,
                ],
            ];

            $children = $this->buildTree($menus, $menu->id);
            if (!empty($children)) {
                $node['children'] = $children;
            }

            $result[] = $node;
        }
        return $result;
    }

    private function fields(Request $request): array
    {
        return $request->only([
            'parent_id', 'menu_type', 'title', 'name', 'path', 'component',
            'redirect', 'icon', 'extra_icon', 'auths', 'frame_src',
            'enter_transition', 'leave_transition', 'active_path', 'rank',
            'frame_loading', 'keep_alive', 'hidden_tag', 'fixed_tag',
            'show_link', 'show_parent',
        ]);
    }

    private function format(SysMenu $m): array
    {
        return [
            'id'              => $m->id,
            'parentId'        => $m->parent_id,
            'menuType'        => $m->menu_type,
            'title'           => $m->title,
            'name'            => $m->name,
            'path'            => $m->path,
            'component'       => $m->component,
            'redirect'        => $m->redirect,
            'icon'            => $m->icon,
            'extraIcon'       => $m->extra_icon,
            'auths'           => $m->auths,
            'frameSrc'        => $m->frame_src,
            'enterTransition' => $m->enter_transition,
            'leaveTransition' => $m->leave_transition,
            'activePath'      => $m->active_path,
            'rank'            => $m->rank,
            'frameLoading'    => $m->frame_loading,
            'keepAlive'       => $m->keep_alive,
            'hiddenTag'       => $m->hidden_tag,
            'fixedTag'        => $m->fixed_tag,
            'showLink'        => $m->show_link,
            'showParent'      => $m->show_parent,
        ];
    }
}
