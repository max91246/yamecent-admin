<?php

namespace App\Http\Controllers\Admin;

use App\AvActress;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AvActressController extends Controller
{
    /**
     * AV 速報：依出道年份 / 爬取時間呈現最新出道女優
     */
    public function news(Request $request)
    {
        $period = $request->input('period', 'year'); // month / quarter / year / all
        $now    = now();

        $query = AvActress::query()->whereNotNull('debut_year');

        switch ($period) {
            case 'month':
                // 本月爬取的新人
                $query->whereMonth('created_at', $now->month)
                      ->whereYear('created_at', $now->year);
                break;
            case 'quarter':
                // 本季爬取的新人
                $quarterStart = $now->copy()->firstOfQuarter();
                $query->where('created_at', '>=', $quarterStart);
                break;
            case 'year':
                $query->where('debut_year', $now->year);
                break;
        }

        $list = $query->orderBy('created_at', 'desc')->orderBy('id', 'desc')
                      ->paginate(24)->appends($request->query());

        // 統計
        $stats = [
            'month'   => AvActress::whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count(),
            'quarter' => AvActress::where('created_at', '>=', $now->copy()->firstOfQuarter())->count(),
            'year'    => AvActress::where('debut_year', $now->year)->count(),
            'total'   => AvActress::count(),
        ];

        return view('admin.av_news', compact('list', 'period', 'stats'));
    }

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
