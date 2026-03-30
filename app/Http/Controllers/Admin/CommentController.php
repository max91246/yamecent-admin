<?php

namespace App\Http\Controllers\Admin;

use App\Article;
use App\ArticleComment;
use App\Member;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function commentList(Request $request)
    {
        $sort      = $request->input('sort', 'desc') === 'asc' ? 'asc' : 'desc';
        $memberId  = $request->input('member_id');
        $articleId = $request->input('article_id');

        $query = ArticleComment::with(['article:id,title', 'member:id,nickname'])
            ->orderBy('created_at', $sort);

        if ($memberId) {
            $query->where('member_id', $memberId);
        }

        if ($articleId) {
            $query->where('article_id', $articleId);
        }

        return view('admin.comment_list', [
            'list'           => $query->paginate(15)->appends($request->query()),
            'selectedMember' => $memberId ? Member::find($memberId) : null,
            'articles'       => Article::orderBy('id', 'desc')->get(['id', 'title']),
        ]);
    }

    public function commentDel($id)
    {
        ArticleComment::findOrFail($id)->delete();
        return $this->json(200, '刪除成功');
    }

    public function commentToggle($id)
    {
        $comment = ArticleComment::findOrFail($id);
        $comment->is_visible = $comment->is_visible ? 0 : 1;
        $comment->save();
        return $this->json(200, $comment->is_visible ? '已顯示' : '已隱藏', ['is_visible' => $comment->is_visible]);
    }

    public function commentReply(Request $request, $id)
    {
        $comment = ArticleComment::findOrFail($id);
        $reply   = trim($request->input('reply', ''));
        if ($reply === '') {
            return $this->json(422, '回復內容不能為空');
        }
        $comment->admin_reply      = $reply;
        $comment->admin_replied_at = now();
        $comment->save();
        return $this->json(200, '回復成功');
    }
}
