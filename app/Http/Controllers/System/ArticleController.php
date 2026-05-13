<?php

namespace App\Http\Controllers\System;

use App\Article;
use App\ArticleComment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    private static $TYPES = [1 => '一般文章', 2 => '高級文章', 3 => '特級文章'];

    // ── 文章 ──────────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = Article::query();

        if ($title = $request->input('title')) {
            $query->where('title', 'like', "%{$title}%");
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->input('is_active'));
        }

        $pageSize    = (int)$request->input('pageSize', 10);
        $currentPage = (int)$request->input('currentPage', 1);
        $paginator   = $query->orderBy('id', 'desc')->paginate($pageSize, ['*'], 'page', $currentPage);

        return response()->json([
            'success' => true,
            'data'    => [
                'list'        => collect($paginator->items())->map(fn($a) => $this->formatArticle($a)),
                'total'       => $paginator->total(),
                'pageSize'    => $pageSize,
                'currentPage' => $currentPage,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $article = Article::create($request->only(['title', 'image', 'content', 'type', 'is_active']));
        return response()->json(['success' => true, 'data' => $this->formatArticle($article)]);
    }

    public function update(Request $request, $id)
    {
        $article = Article::findOrFail($id);
        $article->update($request->only(['title', 'image', 'content', 'type', 'is_active']));
        return response()->json(['success' => true, 'data' => $this->formatArticle($article->fresh())]);
    }

    public function destroy($id)
    {
        Article::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    // ── 留言 ──────────────────────────────────────────────────

    public function comments(Request $request)
    {
        $query = ArticleComment::with('article:id,title');

        if ($request->filled('article_id')) {
            $query->where('article_id', $request->input('article_id'));
        }
        if ($request->filled('is_visible')) {
            $query->where('is_visible', $request->input('is_visible'));
        }

        $pageSize    = (int)$request->input('pageSize', 10);
        $currentPage = (int)$request->input('currentPage', 1);
        $paginator   = $query->orderBy('id', 'desc')->paginate($pageSize, ['*'], 'page', $currentPage);

        return response()->json([
            'success' => true,
            'data'    => [
                'list'        => collect($paginator->items())->map(fn($c) => $this->formatComment($c)),
                'total'       => $paginator->total(),
                'pageSize'    => $pageSize,
                'currentPage' => $currentPage,
            ],
        ]);
    }

    public function updateComment(Request $request, $id)
    {
        $comment = ArticleComment::findOrFail($id);
        $comment->update($request->only(['is_visible', 'admin_reply']));
        if ($request->filled('admin_reply')) {
            $comment->admin_replied_at = now();
            $comment->save();
        }
        return response()->json(['success' => true, 'data' => $this->formatComment($comment->fresh())]);
    }

    public function destroyComment($id)
    {
        ArticleComment::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    // ── format ────────────────────────────────────────────────

    private function formatArticle(Article $a): array
    {
        return [
            'id'               => $a->id,
            'title'            => $a->title,
            'image'            => $a->image,
            'type'             => $a->type,
            'typeLabel'        => self::$TYPES[$a->type] ?? '-',
            'isActive'         => $a->is_active,
            'sourceMemberId'   => $a->source_member_id,
            'sourcePublishedAt'=> $a->source_published_at?->format('Y-m-d H:i:s'),
            'createdAt'        => $a->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function formatComment(ArticleComment $c): array
    {
        return [
            'id'             => $c->id,
            'articleId'      => $c->article_id,
            'articleTitle'   => $c->article?->title,
            'memberId'       => $c->member_id,
            'content'        => $c->content,
            'isVisible'      => $c->is_visible,
            'adminReply'     => $c->admin_reply,
            'adminRepliedAt' => $c->admin_replied_at?->format('Y-m-d H:i:s'),
            'createdAt'      => $c->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
