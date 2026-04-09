<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Member;
use App\Article;
use App\ArticleComment;
use App\TgWallet;
use App\TgHolding;
use App\TgHoldingTrade;
use App\TgSettlement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Storage;

class IndexController extends Controller
{
    public function index()
    {
        $admin    = session('admin');
        $menuList = $admin->getMenus();
        return view('admin.index', ['menu' => $menuList]);
    }
    public function console()
    {
        $today = Carbon::today();

        $holdingCount = TgHolding::count();
        $marginCount  = TgHolding::where('is_margin', 1)->count();
        $tradeTotal   = TgHoldingTrade::count();
        $tradeWin     = TgHoldingTrade::where('profit', '>', 0)->count();

        $stats = [
            // 會員內容
            'member_total'    => Member::count(),
            'member_active'   => Member::where('is_active', 1)->count(),
            'member_paid'     => Member::where('is_member', 1)->where('member_expired_at', '>', now())->count(),
            'article_total'   => Article::where('is_active', 1)->count(),
            'comment_today'   => ArticleComment::whereDate('created_at', $today)->count(),
            // TG Bot
            'bot_users'       => TgWallet::distinct('tg_chat_id')->count('tg_chat_id'),
            'holding_users'   => TgHolding::distinct('tg_chat_id')->count('tg_chat_id'),
            'holding_cost'    => TgHolding::sum('total_cost'),
            'holding_count'   => $holdingCount,
            'margin_count'    => $marginCount,
            'margin_pct'      => $holdingCount > 0 ? round($marginCount / $holdingCount * 100) : 0,
            'trade_total'     => $tradeTotal,
            'trade_profit'    => TgHoldingTrade::sum('profit'),
            'trade_win'       => $tradeWin,
            'trade_win_pct'   => $tradeTotal > 0 ? round($tradeWin / $tradeTotal * 100) : 0,
            // 交割款
            'settle_pending'  => TgSettlement::where('is_settled', 0)->count(),
            'settle_buy_amt'  => TgSettlement::where('is_settled', 0)->where('direction', 'buy')->sum('settlement_amount'),
            'settle_sell_amt' => TgSettlement::where('is_settled', 0)->where('direction', 'sell')->sum('settlement_amount'),
        ];

        $recentTrades = TgHoldingTrade::orderBy('created_at', 'desc')->limit(10)->get();

        return view('admin.console', compact('stats', 'recentTrades'));
    }

    /**
     * @Desc: 后台图片上传
     * @Author: woann <304550409@qq.com>
     * @param Request $request
     * @return mixed
     */
    public function upload(Request $request)
    {
        $file = $request->file('image');
        $path = $request->input('path') . '/';
        if ($file) {
            if ($file->isValid()) {
                $size = $file->getSize();
                if ($size > 5000000) {
                    return $this->json(500, '图片不能大于5M！');
                }
                // 获取文件相关信息
                $ext = $file->getClientOriginalExtension(); // 扩展名
                if (!in_array($ext, ['png', 'jpg', 'gif', 'jpeg', 'pem'])) {
                    return $this->json(500, '文件类型不正确！');
                }
                $realPath = $file->getRealPath(); //临时文件的绝对路径
                // 上传文件
                $filename = $path . date('Ymd') . '/' . uniqid() . '.' . $ext;
                // 使用我们新建的uploads本地存储空间（目录）
                $bool = Storage::disk('admin')->put($filename, file_get_contents($realPath));
                if ($bool) {
                    return $this->json(200, '上传成功', ['filename' => '/uploads/' . $filename]);
                } else {
                    return $this->json(500, '上传失败！');
                }
            } else {
                return $this->json(500, '文件类型不正确！');
            }
        } else {
            return $this->json(500, '上传失败！');
        }
    }

    /**
     * @Desc: 富文本上传图片
     * @Author: woann <304550409@qq.com>
     * @param Request $request
     */
    public function wangeditorUpload(Request $request)
    {
        $file = $request->file('wangEditorH5File');
        if ($file) {
            if ($file->isValid()) {
                // 获取文件相关信息
                $ext      = $file->getClientOriginalExtension(); // 扩展名
                $realPath = $file->getRealPath(); //临时文件的绝对路径
                // 上传文件
                $filename = date('Ymd') . '/' . uniqid() . '.' . $ext;
                // 使用我们新建的uploads本地存储空间（目录）
                $bool = Storage::disk('admin')->put('/wangeditor/' . $filename, file_get_contents($realPath));
                if ($bool) {
                    echo asset('/uploads/wangeditor/' . $filename);
                } else {
                    echo 'error|上传失败';
                }
            } else {
                echo 'error|上传失败';
            }
        } else {
            echo 'error|图片类型不正确';
        }
    }

    /**
     * @Desc: 无权限界面
     * @Author: woann <304550409@qq.com>
     * @return \Illuminate\View\View
     */
    public function noPermission()
    {
        return view('base.403');
    }
}
