<?php

namespace App\Http\Controllers\Admin;

use App\AvVideo;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AvVideoController extends Controller
{
    public function news(Request $request)
    {
        $period = $request->input('period', 'week');
        $now    = now();

        $query = AvVideo::query();

        switch ($period) {
            case 'today':
                $query->whereDate('release_date', $now->toDateString());
                break;
            case 'week':
                $query->where('release_date', '>=', $now->copy()->subDays(7)->toDateString());
                break;
            case 'month':
                $query->where('release_date', '>=', $now->copy()->subDays(30)->toDateString());
                break;
        }

        if ($code = $request->input('code')) {
            $query->where('code', 'like', "%{$code}%");
        }
        if ($actress = $request->input('actress')) {
            $query->where('actresses', 'like', "%{$actress}%");
        }
        if ($studio = $request->input('studio')) {
            $query->where('studio', 'like', "%{$studio}%");
        }
        if ($tags = array_filter((array) $request->input('tags', []))) {
            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }
        if ($request->boolean('uncensored')) {
            $query->where('is_uncensored', true);
        }

        $list = $query->orderBy('release_date', 'desc')->orderBy('id', 'desc')
                      ->paginate(24)->appends($request->query());

        // 從 Redis 快取取熱門標籤清單（同 bot 共用同一份）
        $availableTags = Cache::remember('av_popular_tags', 3600, function () {
            $rows  = AvVideo::whereNotNull('tags')->pluck('tags');
            $count = [];
            foreach ($rows as $tagArr) {
                if (!is_array($tagArr)) continue;
                foreach ($tagArr as $tag) {
                    $t = trim($tag);
                    if ($t && mb_strlen($t) <= 10) $count[$t] = ($count[$t] ?? 0) + 1;
                }
            }
            arsort($count);
            return array_keys(array_slice($count, 0, 40, true));
        });

        $stats = [
            'today' => AvVideo::whereDate('release_date', $now->toDateString())->count(),
            'week'  => AvVideo::where('release_date', '>=', $now->copy()->subDays(7)->toDateString())->count(),
            'month' => AvVideo::where('release_date', '>=', $now->copy()->subDays(30)->toDateString())->count(),
            'total' => AvVideo::count(),
        ];

        return view('admin.av_video_list', compact('list', 'period', 'stats', 'availableTags'));
    }
}
