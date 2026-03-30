<?php

namespace App\Http\Controllers\Admin;

use App\Article;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function articleList()
    {
        return view('admin.article_list', [
            'list' => Article::orderBy('id', 'desc')->paginate(10),
        ]);
    }

    public function articleAddView()
    {
        return view('admin.article_add');
    }

    public function articleAdd(Request $request)
    {
        $data = $request->only(['title', 'image', 'content', 'type', 'is_active']);
        if (empty($data['title'])) {
            return $this->json(500, '請填寫標題');
        }
        $data['is_active'] = isset($data['is_active']) ? 1 : 0;
        $article = new Article();
        $article->fill($data);
        $article->save();
        return $this->json(200, '新增成功');
    }

    public function articleUpdateView(Request $request, $id)
    {
        return view('admin.article_update', [
            'article' => Article::findOrFail($id),
        ]);
    }

    public function articleUpdate(Request $request, $id)
    {
        $data = $request->only(['title', 'image', 'content', 'type', 'is_active']);
        if (empty($data['title'])) {
            return $this->json(500, '請填寫標題');
        }
        $data['is_active'] = isset($data['is_active']) ? 1 : 0;
        $article = Article::findOrFail($id);
        $article->fill($data)->save();
        return $this->json(200, '修改成功');
    }

    public function articleDel($id)
    {
        Article::findOrFail($id)->delete();
        return $this->json(200, '刪除成功');
    }
}
