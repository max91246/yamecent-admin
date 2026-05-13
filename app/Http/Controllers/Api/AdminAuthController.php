<?php

namespace App\Http\Controllers\Api;

use App\SysUser;
use App\Http\Controllers\Controller;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;

class AdminAuthController extends Controller
{
    public function login(Request $request)
    {
        $username = $request->input('username', '');
        $password = $request->input('password', '');

        if (empty($username) || empty($password)) {
            return response()->json(['success' => false, 'msg' => '帳號和密碼不能為空'], 422);
        }

        $user = SysUser::where('username', $username)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return response()->json(['success' => false, 'msg' => '帳號或密碼錯誤'], 401);
        }

        if (!$user->status) {
            return response()->json(['success' => false, 'msg' => '帳號已停用'], 403);
        }

        $secret     = config('services.jwt.secret');
        $now        = time();
        $accessTtl  = 7200;
        $refreshTtl = 604800;
        $accessJti  = uniqid('adm_', true);
        $refreshJti = uniqid('ref_', true);

        $accessToken = JWT::encode([
            'sub'      => $user->id,
            'username' => $user->username,
            'type'     => 'admin',
            'jti'      => $accessJti,
            'iat'      => $now,
            'exp'      => $now + $accessTtl,
        ], $secret, 'HS256');

        $refreshToken = JWT::encode([
            'sub'  => $user->id,
            'type' => 'admin_refresh',
            'jti'  => $refreshJti,
            'iat'  => $now,
            'exp'  => $now + $refreshTtl,
        ], $secret, 'HS256');

        Redis::setex('admin_token:' . $accessJti, $accessTtl, $user->id);

        $roles       = $user->roles()->pluck('code')->toArray() ?: ['common'];
        $permissions = in_array('admin', $roles) ? ['*:*:*'] : [];

        return response()->json([
            'success' => true,
            'data'    => [
                'avatar'       => $user->avatar,
                'username'     => $user->username,
                'nickname'     => $user->nickname,
                'roles'        => $roles,
                'permissions'  => $permissions,
                'accessToken'  => $accessToken,
                'refreshToken' => $refreshToken,
                'expires'      => date('Y/m/d H:i:s', $now + $accessTtl),
            ],
        ]);
    }

    public function refreshToken(Request $request)
    {
        $token  = $request->input('refreshToken', '');
        $secret = config('services.jwt.secret');

        try {
            $payload = JWT::decode($token, new Key($secret, 'HS256'));
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'msg' => 'refreshToken 無效'], 401);
        }

        if (($payload->type ?? '') !== 'admin_refresh') {
            return response()->json(['success' => false, 'msg' => 'refreshToken 類型錯誤'], 401);
        }

        $user = SysUser::find($payload->sub);
        if (!$user) {
            return response()->json(['success' => false, 'msg' => '用戶不存在'], 401);
        }

        $now        = time();
        $accessTtl  = 7200;
        $refreshTtl = 604800;
        $accessJti  = uniqid('adm_', true);
        $refreshJti = uniqid('ref_', true);

        $accessToken = JWT::encode([
            'sub'      => $user->id,
            'username' => $user->username,
            'type'     => 'admin',
            'jti'      => $accessJti,
            'iat'      => $now,
            'exp'      => $now + $accessTtl,
        ], $secret, 'HS256');

        $refreshToken = JWT::encode([
            'sub'  => $user->id,
            'type' => 'admin_refresh',
            'jti'  => $refreshJti,
            'iat'  => $now,
            'exp'  => $now + $refreshTtl,
        ], $secret, 'HS256');

        Redis::setex('admin_token:' . $accessJti, $accessTtl, $user->id);

        return response()->json([
            'success' => true,
            'data'    => [
                'accessToken'  => $accessToken,
                'refreshToken' => $refreshToken,
                'expires'      => date('Y/m/d H:i:s', $now + $accessTtl),
            ],
        ]);
    }
}
