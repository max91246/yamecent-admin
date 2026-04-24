<?php

namespace App\Http\Controllers\Admin;

use App\AvActress;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AvActressController extends Controller
{
    public function index(Request $request)
    {
        $query = AvActress::query();

        // 姓名
        if ($name = $request->input('name')) {
            $query->where('name', 'like', "%{$name}%");
        }

        // 狀態
        if ($request->input('is_active') !== null && $request->input('is_active') !== '') {
            $query->where('is_active', $request->input('is_active'));
        }

        // 出道年
        if ($debutYear = $request->input('debut_year')) {
            $query->where('debut_year', $debutYear);
        }

        // 身高範圍
        if ($heightMin = $request->input('height_min')) {
            $query->where('height', '>=', (int) $heightMin);
        }
        if ($heightMax = $request->input('height_max')) {
            $query->where('height', '<=', (int) $heightMax);
        }

        // 腰圍範圍
        if ($waistMin = $request->input('waist_min')) {
            $query->where('waist', '>=', (int) $waistMin);
        }
        if ($waistMax = $request->input('waist_max')) {
            $query->where('waist', '<=', (int) $waistMax);
        }

        // 罩杯（bust 欄位含字母，如 35E，用 LIKE 比對字母部分）
        if ($cup = $request->input('cup')) {
            $query->where('bust', 'like', '%' . strtoupper($cup));
        }

        $list = $query->orderBy('debut_year', 'desc')->orderBy('id', 'desc')
                      ->paginate(24)->appends($request->query());

        return view('admin.av_actress_list', compact('list'));
    }
}
