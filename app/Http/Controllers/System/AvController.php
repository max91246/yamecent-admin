<?php

namespace App\Http\Controllers\System;

use App\AvActress;
use App\AvVideo;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AvController extends Controller
{
    // ── 影片 ──────────────────────────────────────────────────

    public function popularTags()
    {
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
        $tags = array_keys(array_slice($count, 0, 60, true));
        return response()->json(['success' => true, 'data' => $tags]);
    }

    public function videos(Request $request)
    {
        $query = AvVideo::query();
        if ($code    = $request->input('code'))    $query->where('code', 'like', "%{$code}%");
        if ($actress = $request->input('actress')) $query->where('actresses', 'like', "%{$actress}%");
        if ($studio  = $request->input('studio'))  $query->where('studio', 'like', "%{$studio}%");
        if ($request->filled('is_uncensored'))     $query->where('is_uncensored', $request->input('is_uncensored'));
        if ($tags = array_filter((array) $request->input('tags', []))) {
            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        $pageSize    = (int)$request->input('pageSize', 20);
        $currentPage = (int)$request->input('currentPage', 1);
        $paginator   = $query->orderBy('created_at', 'desc')->orderBy('id', 'desc')
                             ->paginate($pageSize, ['*'], 'page', $currentPage);

        return response()->json([
            'success' => true,
            'data'    => [
                'list'        => collect($paginator->items())->map(fn($v) => $this->formatVideo($v)),
                'total'       => $paginator->total(),
                'pageSize'    => $pageSize,
                'currentPage' => $currentPage,
            ],
        ]);
    }

    public function updateVideo(Request $request, $id)
    {
        $video = AvVideo::findOrFail($id);
        $video->update($request->only(['title', 'studio', 'series', 'is_uncensored', 'is_leaked']));
        return response()->json(['success' => true, 'data' => $this->formatVideo($video->fresh())]);
    }

    public function destroyVideo($id)
    {
        AvVideo::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    // ── 女優 ──────────────────────────────────────────────────

    public function actresses(Request $request)
    {
        $query = AvActress::withCount('videos');
        if ($name = $request->input('name'))           $query->where('name', 'like', "%{$name}%");
        if ($request->filled('is_active'))             $query->where('is_active', $request->input('is_active'));
        if ($debutYear = $request->input('debut_year')) $query->where('debut_year', $debutYear);
        if ($bust = $request->input('bust'))           $query->where('bust', 'like', "%{$bust}%");
        if ($heightMin = $request->input('height_min')) $query->where('height', '>=', (int)$heightMin);
        if ($heightMax = $request->input('height_max')) $query->where('height', '<=', (int)$heightMax);
        if ($request->filled('has_image')) {
            if ($request->input('has_image') == 1) $query->whereNotNull('image_url')->where('image_url', '!=', '');
            else $query->where(fn($q) => $q->whereNull('image_url')->orWhere('image_url', ''));
        }

        $pageSize    = (int)$request->input('pageSize', 20);
        $currentPage = (int)$request->input('currentPage', 1);
        $paginator   = $query->orderBy('created_at', 'desc')->paginate($pageSize, ['*'], 'page', $currentPage);

        return response()->json([
            'success' => true,
            'data'    => [
                'list'        => collect($paginator->items())->map(fn($a) => $this->formatActress($a)),
                'total'       => $paginator->total(),
                'pageSize'    => $pageSize,
                'currentPage' => $currentPage,
            ],
        ]);
    }

    public function updateActress(Request $request, $id)
    {
        $actress = AvActress::findOrFail($id);
        $actress->update($request->only(['name', 'height', 'bust', 'waist', 'hip', 'birthplace', 'debut_year', 'notes', 'is_active']));
        return response()->json(['success' => true, 'data' => $this->formatActress($actress->fresh())]);
    }

    public function destroyActress($id)
    {
        AvActress::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    // ── format ────────────────────────────────────────────────

    private function formatVideo(AvVideo $v): array
    {
        return [
            'id'           => $v->id,
            'code'         => $v->code,
            'title'        => $v->title,
            'coverUrl'     => $v->cover_url,
            'releaseDate'  => $v->release_date?->format('Y-m-d'),
            'studio'       => $v->studio,
            'series'       => $v->series,
            'durationMin'  => $v->duration_min,
            'actresses'    => $v->actresses ?? [],
            'tags'         => $v->tags ?? [],
            'isUncensored' => $v->is_uncensored,
            'isLeaked'     => $v->is_leaked,
            'createdAt'    => $v->created_at?->format('Y-m-d'),
        ];
    }

    private function formatActress(AvActress $a): array
    {
        return [
            'id'          => $a->id,
            'name'        => $a->name,
            'imageUrl'    => $a->image_url,
            'height'      => $a->height,
            'bust'        => $a->bust,
            'waist'       => $a->waist,
            'hip'         => $a->hip,
            'birthplace'  => $a->birthplace,
            'debutYear'   => $a->debut_year,
            'isActive'    => $a->is_active,
            'videosCount' => $a->videos_count ?? 0,
            'createdAt'   => $a->created_at?->format('Y-m-d'),
        ];
    }
}
