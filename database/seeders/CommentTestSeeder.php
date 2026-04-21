<?php

namespace Database\Seeders;

use App\Article;
use App\ArticleComment;
use App\Member;
use Illuminate\Database\Seeder;

class CommentTestSeeder extends Seeder
{
    public function run()
    {
        $article = Article::where('is_active', 1)->first();
        if (!$article) {
            $this->command->warn('找不到啟用中的文章，請先新增文章再執行此 Seeder。');
            return;
        }

        $members = Member::take(3)->get();
        if ($members->isEmpty()) {
            $this->command->warn('找不到任何會員，請先新增會員再執行此 Seeder。');
            return;
        }

        $sentences = [
            '這篇文章真的很棒！',
            '非常有用的資訊，謝謝分享。',
            '感謝分享！學到很多。',
            '寫得好詳細，期待更多內容。',
            '很有幫助，讚！',
            '內容豐富，值得一看。',
            '解說清楚，易於理解。',
            '受益良多，繼續加油！',
        ];

        $count = $members->count();
        for ($i = 0; $i < 50; $i++) {
            ArticleComment::create([
                'article_id' => $article->id,
                'member_id'  => $members[$i % $count]->id,
                'content'    => $sentences[$i % count($sentences)] . ' #' . ($i + 1),
                'is_visible' => 1,
            ]);
        }

        $this->command->info("已成功插入 50 筆測試留言（文章：{$article->title}）");
    }
}
