<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class ApiAuth
{
    public function handle(Request $request, Closure $next)
    {
        $authorization = $request->header('Authorization', '');

        if (!str_starts_with($authorization, 'Bearer ')) {
            return response()->json(['code' => 401, 'msg' => '請先登入'], 401);
        }

        $token = substr($authorization, 7);

        try {
            $decoded = JWT::decode($token, new Key(config('services.jwt.secret'), 'HS256'));
        } catch (ExpiredException $e) {
            return response()->json(['code' => 401, 'msg' => 'Token 已過期'], 401);
        } catch (\Exception $e) {
            return response()->json(['code' => 401, 'msg' => 'Token 無效'], 401);
        }

        $jti   = $decoded->jti ?? null;
        $exist = $jti ? Redis::get('api_token:' . $jti) : null;

        if (!$exist) {
            return response()->json(['code' => 401, 'msg' => 'Token 已失效'], 401);
        }

        $request->attributes->set('auth_member_id', $decoded->sub);
        $request->attributes->set('jwt_jti', $jti);

        return $next($request);
    }
}
