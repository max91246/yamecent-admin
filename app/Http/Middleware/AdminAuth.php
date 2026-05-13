<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class AdminAuth
{
    public function handle(Request $request, Closure $next)
    {
        $authorization = $request->header('Authorization', '');

        if (!str_starts_with($authorization, 'Bearer ')) {
            return response()->json(['success' => false, 'msg' => '請先登入'], 401);
        }

        $token = substr($authorization, 7);

        try {
            $decoded = JWT::decode($token, new Key(config('services.jwt.secret'), 'HS256'));
        } catch (ExpiredException $e) {
            return response()->json(['success' => false, 'msg' => 'Token 已過期'], 401);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'msg' => 'Token 無效'], 401);
        }

        if (($decoded->type ?? '') !== 'admin') {
            return response()->json(['success' => false, 'msg' => '無權限'], 403);
        }

        $jti   = $decoded->jti ?? null;
        $exist = $jti ? Redis::get('admin_token:' . $jti) : null;

        if (!$exist) {
            return response()->json(['success' => false, 'msg' => 'Token 已失效'], 401);
        }

        $request->attributes->set('admin_id', $decoded->sub);

        return $next($request);
    }
}
