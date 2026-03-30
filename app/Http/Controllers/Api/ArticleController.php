<?php

namespace App\Http\Controllers\Api;

use App\Article;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $query = Article::where('is_active', 1);

        if ($type = $request->input('type')) {
            $query->where('type', (int) $type);
        }

        $paginator = $query->withCount('comments')
            ->orderBy('id', 'desc')
            ->paginate(20, ['id', 'title', 'image', 'type', 'created_at']);

        return response()->json([
            'code' => 200,
            'msg'  => 'success',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    public function show($id)
    {
        $article = Article::where('is_active', 1)->find($id);
        if (!$article) {
            return response()->json(['code' => 404, 'msg' => '文章不存在', 'data' => null]);
        }
        return response()->json([
            'code' => 200,
            'msg'  => 'success',
            'data' => $article,
        ]);
    }
}
