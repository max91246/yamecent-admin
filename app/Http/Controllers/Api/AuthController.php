<?php

namespace App\Http\Controllers\Api;

use App\Member;
use App\Http\Controllers\Controller;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $account  = $request->input('account', '');
        $password = $request->input('password', '');

        if (empty($account) || empty($password)) {
            return response()->json(['code' => 422, 'msg' => '帳號和密碼不能為空'], 422);
        }

        $member = Member::where('account', $account)->first();

        if (!$member || !Hash::check($password, $member->password)) {
            return response()->json(['code' => 401, 'msg' => '帳號或密碼錯誤'], 401);
        }

        if (!$member->is_active) {
            return response()->json(['code' => 403, 'msg' => '帳號已停用'], 403);
        }

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
                'id'               => $member->id,
                'account'          => $member->account,
                'avatar'           => $member->avatar,
                'nickname'         => $member->nickname,
                'is_member'        => (int) $member->is_member,
                'member_expired_at'=> $member->member_expired_at ? (string) $member->member_expired_at : null,
                'member_applied_at'=> $member->member_applied_at ? (string) $member->member_applied_at : null,
                'is_member_active' => $member->isMemberActive(),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $jti = $request->attributes->get('jwt_jti');
        if ($jti) {
            Redis::del('api_token:' . $jti);
        }

        return response()->json(['code' => 200, 'msg' => '登出成功']);
    }
}
