<?php
namespace App\Http\Controllers\Admin;

use App\AdminConfig;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TestController extends Controller
{
    /**
     * @Desc: 配置列表
     * @Author: woann <304550409@qq.com>
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function list(Request $request)
    {
        $wd   = $request->input('wd');
        $list = AdminConfig::searchCondition($wd)->paginate(10);
        return view('admin.test_list', ['list' => $list, 'wd' => $wd]);
    }

}
