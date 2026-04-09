<?php

namespace App\Http\Controllers\Api;

use App\Member;
use App\Http\Controllers\Controller;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class TgAuthController extends Controller
{
    // POST /api/auth/tg-login
    // body: { init_data: "..." }  （Telegram.WebApp.initData）
    public function login(Request $request)
    {
        $initData = $request->input('init_data', '');

        if (empty($initData)) {
            return response()->json(['code' => 422, 'msg' => 'init_data 不能為空'], 422);
        }

        // ── 驗證 initData 合法性 ────────────────────────────────
        $botToken = config('services.telegram.bot_token');
        if (!$this->validateInitData($initData, $botToken)) {
            return response()->json(['code' => 401, 'msg' => 'Telegram 驗證失敗'], 401);
        }

        // ── 解析用戶資訊 ────────────────────────────────────────
        parse_str($initData, $params);
        $userJson = $params['user'] ?? null;
        if (!$userJson) {
            return response()->json(['code' => 422, 'msg' => '無法取得用戶資訊'], 422);
        }

        $tgUser = json_decode($userJson, true);
        $tgId   = $tgUser['id'] ?? null;

        if (!$tgId) {
            return response()->json(['code' => 422, 'msg' => '無效的 Telegram 用戶'], 422);
        }

        // ── 查找或自動建立會員 ──────────────────────────────────
        $account  = 'tg_' . $tgId;
        $nickname = trim(($tgUser['first_name'] ?? '') . ' ' . ($tgUser['last_name'] ?? '')) ?: $account;

        $member = Member::firstOrCreate(
            ['account' => $account],
            [
                'nickname'  => $nickname,
                'password'  => null,
                'is_active' => 1,
            ]
        );

        if (!$member->is_active) {
            return response()->json(['code' => 403, 'msg' => '帳號已停用'], 403);
        }

        // 自動更新暱稱（TG 用戶名可能有改）
        if ($member->nickname !== $nickname) {
            $member->update(['nickname' => $nickname]);
        }

        // ── 發行 JWT ────────────────────────────────────────────
        $ttl    = (int) config('services.jwt.ttl', 604800);
        $secret = config('services.jwt.secret');
        $jti    = uniqid('jwt_', true);
        $now    = time();

        $payload = [
            'sub'     => $member->id,
            'account' => $member->account,
            'jti'     => $jti,
            'iat'     => $now,
            'exp'     => $now + $ttl,
        ];

        $token = JWT::encode($payload, $secret, 'HS256');
        Redis::setex('api_token:' . $jti, $ttl, $member->id);

        return response()->json([
            'code'       => 200,
            'msg'        => '登入成功',
            'token'      => $token,
            'expires_in' => $ttl,
            'member'     => [
                'id'                => $member->id,
                'account'           => $member->account,
                'avatar'            => $member->avatar,
                'nickname'          => $member->nickname,
                'is_member'         => (int) $member->is_member,
                'member_expired_at' => $member->member_expired_at ? (string) $member->member_expired_at : null,
                'member_applied_at' => $member->member_applied_at ? (string) $member->member_applied_at : null,
                'is_member_active'  => $member->isMemberActive(),
            ],
        ]);
    }

    // ── HMAC-SHA256 驗證 initData ────────────────────────────────
    private function validateInitData(string $initData, string $botToken): bool
    {
        parse_str($initData, $params);
        $hash = $params['hash'] ?? '';
        unset($params['hash']);

        // 依照 key 排序，組成 data-check-string
        ksort($params);
        $checkString = implode("\n", array_map(
            fn($k, $v) => "{$k}={$v}",
            array_keys($params),
            array_values($params)
        ));

        $secretKey    = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $expectedHash = bin2hex(hash_hmac('sha256', $checkString, $secretKey, true));

        return hash_equals($expectedHash, $hash);
    }
}
