<?php

namespace App\Http\Controllers\Api;

use App\Article;
use App\ArticleComment;
use App\Member;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class CommentController extends Controller
{
    /**
     * GET /api/articles/{id}/comments  (公開)
     */
    public function index(Request $request, $id)
    {
        $article = Article::where('is_active', 1)->find($id);
        if (!$article) {
            return response()->json(['code' => 404, 'msg' => '文章不存在', 'data' => null]);
        }

        $paginator = ArticleComment::where('article_id', $id)
            ->where('is_visible', 1)
            ->with('member:id,nickname,avatar')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $items = collect($paginator->items())->map(function ($c) {
            return [
                'id'               => $c->id,
                'content'          => $c->content,
                'created_at'       => (string) $c->created_at,
                'admin_reply'      => $c->admin_reply,
                'admin_replied_at' => $c->admin_replied_at ? (string) $c->admin_replied_at : null,
                'member'           => [
                    'id'       => $c->member->id,
                    'nickname' => $c->member->nickname,
                    'avatar'   => $c->member->avatar,
                ],
            ];
        });

        return response()->json([
            'code' => 200,
            'msg'  => 'success',
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /**
     * POST /api/articles/{id}/comments  (需要登入)
     */
    public function store(Request $request, $id)
    {
        $article = Article::where('is_active', 1)->find($id);
        if (!$article) {
            return response()->json(['code' => 404, 'msg' => '文章不存在', 'data' => null]);
        }

        $memberId = $request->attributes->get('auth_member_id');
        $member   = Member::find($memberId);
        if (!$member || $member->can_comment == 0) {
            return response()->json(['code' => 403, 'msg' => '您已被禁止留言', 'data' => null]);
        }

        $cooldownKey = 'comment_cooldown:' . $memberId;
        $ttl         = Redis::ttl($cooldownKey);

        if ($ttl > 0) {
            return response()->json([
                'code' => 429,
                'msg'  => '請等待 ' . $ttl . ' 秒後再留言',
                'data' => ['wait_seconds' => $ttl],
            ]);
        }

        $content = trim($request->input('content', ''));
        if ($content === '') {
            return response()->json(['code' => 422, 'msg' => '留言內容不能為空', 'data' => null]);
        }
        if (mb_strlen($content) > 500) {
            return response()->json(['code' => 422, 'msg' => '留言內容不能超過 500 字', 'data' => null]);
        }

        $comment = ArticleComment::create([
            'article_id' => $id,
            'member_id'  => $memberId,
            'content'    => $content,
            'is_visible' => 1,
        ]);

        Redis::setex($cooldownKey, 30, 1);

        $comment->load('member:id,nickname,avatar');

        return response()->json([
            'code' => 200,
            'msg'  => '留言成功',
            'data' => [
                'id'               => $comment->id,
                'content'          => $comment->content,
                'created_at'       => (string) $comment->created_at,
                'admin_reply'      => null,
                'admin_replied_at' => null,
                'member'           => [
                    'id'       => $comment->member->id,
                    'nickname' => $comment->member->nickname,
                    'avatar'   => $comment->member->avatar,
                ],
            ],
        ]);
    }
}
