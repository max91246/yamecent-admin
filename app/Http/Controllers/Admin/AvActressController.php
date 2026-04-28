<?php

namespace App\Http\Controllers\Admin;

use App\AvActress;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AvActressController extends Controller
{
    public function index(Request $request)
    {
        $now    = now();
        $period = $request->input('period', ''); // month / quarter / year

        $query = AvActress::query();

        // 姓名
        if ($name = $request->input('name')) {
            $query->where('name', 'like', "%{$name}%");
        }

        // 出道年
        if ($debutYear = $request->input('debut_year')) {
            $query->where('debut_year', $debutYear);
        }

        // 身高範圍
        if ($hMin = $request->input('height_min')) $query->where('height', '>=', (int) $hMin);
        if ($hMax = $request->input('height_max')) $query->where('height', '<=', (int) $hMax);

        // 腰圍範圍
        if ($wMin = $request->input('waist_min')) $query->where('waist', '>=', (int) $wMin);
        if ($wMax = $request->input('waist_max')) $query->where('waist', '<=', (int) $wMax);

        // 罩杯
        if ($cup = $request->input('cup')) {
            $query->where('bust', 'like', '%' . strtoupper($cup));
        }

        // 期間篩選（按爬取時間）
        switch ($period) {
            case 'month':
                $query->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year);
                break;
            case 'quarter':
                $query->where('created_at', '>=', $now->copy()->firstOfQuarter());
                break;
            case 'year':
                $query->where('debut_year', $now->year);
                break;
        }

        $list = $query->orderBy('created_at', 'desc')->paginate(24)->appends($request->query());

        // 統計卡
        $stats = [
            'month'   => AvActress::whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count(),
            'quarter' => AvActress::where('created_at', '>=', $now->copy()->firstOfQuarter())->count(),
            'year'    => AvActress::where('debut_year', $now->year)->count(),
            'total'   => AvActress::count(),
        ];

        return view('admin.av_actress_list', compact('list', 'period', 'stats'));
    }
}
