<?php

namespace App\Http\Controllers\Admin;

use App\TgBot;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TgBotController extends Controller
{
    public function tgBotList(Request $request)
    {
        $query = TgBot::query();

        if ($name = $request->input('name')) {
            $query->where('name', 'like', "%{$name}%");
        }

        if ($request->input('is_active') !== null && $request->input('is_active') !== '') {
            $query->where('is_active', $request->input('is_active'));
        }

        $query->orderBy('id', 'desc');

        return view('admin.tg_bot_list', [
            'list' => $query->paginate(10)->appends($request->query()),
        ]);
    }

    public function tgBotAdd()
    {
        return view('admin.tg_bot_add');
    }

    public function tgBotAddPost(Request $request)
    {
        $name     = trim($request->input('name', ''));
        $token    = trim($request->input('token', ''));
        $type     = (int) $request->input('type', 1);
        $isActive = (int) $request->input('is_active', 1);
        $remark   = trim($request->input('remark', ''));

        if (empty($name)) {
            return $this->json(500, '請填寫機器人名稱');
        }
        if (empty($token)) {
            return $this->json(500, '請填寫 Bot Token');
        }
        if (TgBot::where('token', $token)->exists()) {
            return $this->json(500, '此 Token 已存在');
        }

        $bot = TgBot::create([
            'name'      => $name,
            'token'     => $token,
            'type'      => $type,
            'is_active' => $isActive,
            'remark'    => $remark ?: null,
        ]);

        $this->setWebhook($bot);

        return $this->json(200, '新增成功');
    }

    public function tgBotUpdate($id)
    {
        return view('admin.tg_bot_update', [
            'bot' => TgBot::findOrFail($id),
        ]);
    }

    public function tgBotUpdatePost(Request $request, $id)
    {
        $bot      = TgBot::findOrFail($id);
        $name     = trim($request->input('name', ''));
        $token    = trim($request->input('token', ''));
        $type     = (int) $request->input('type', 1);
        $isActive = (int) $request->input('is_active', 1);
        $remark   = trim($request->input('remark', ''));

        if (empty($name)) {
            return $this->json(500, '請填寫機器人名稱');
        }
        if (empty($token)) {
            return $this->json(500, '請填寫 Bot Token');
        }
        if (TgBot::where('token', $token)->where('id', '!=', $id)->exists()) {
            return $this->json(500, '此 Token 已存在');
        }

        $tokenChanged = ($bot->token !== $token);

        $bot->fill([
            'name'      => $name,
            'token'     => $token,
            'type'      => $type,
            'is_active' => $isActive,
            'remark'    => $remark ?: null,
        ])->save();

        if ($tokenChanged) {
            $this->setWebhook($bot->fresh());
        }

        return $this->json(200, '修改成功');
    }

    public function tgBotDel($id)
    {
        TgBot::findOrFail($id)->delete();
        return $this->json(200, '刪除成功');
    }

    private function setWebhook(TgBot $bot): bool
    {
        $webhookUrl = rtrim(config('app.url'), '/') . '/api/tg/webhook/' . $bot->id;

        try {
            $response = Http::timeout(10)->post(
                "https://api.telegram.org/bot{$bot->token}/setWebhook",
                ['url' => $webhookUrl]
            );

            if ($response->successful() && $response->json('ok') === true) {
                $bot->update(['webhook_set_at' => now()]);
                return true;
            }
        } catch (\Exception $e) {
            // webhook 設定失敗不阻止機器人建立
        }

        return false;
    }
}
