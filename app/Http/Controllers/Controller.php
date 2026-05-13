<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    /**
     * @Author woann <304550409@qq.com>
     * @param int $code
     * @param string $msg
     * @param array $data
     * @return mixed
     * @description 接口返回数据格式
     */
    protected function safeDate($date, string $format = 'Y-m-d H:i:s'): ?string
    {
        if (!$date) return null;
        if ($date instanceof \Carbon\Carbon) return $date->format($format);
        try { return \Carbon\Carbon::parse($date)->format($format); } catch (\Exception $e) { return null; }
    }

    protected function json($code = 200, $msg = '', $data = [])
    {
        if ($data == []) {
            $res = [
                'code' => $code,
                'msg'  => $msg,
            ];
        } else {
            $res = [
                'code' => $code,
                'msg'  => $msg,
                'data' => $data,
            ];
        }
        return response()->json($res)->header('Content-Type', 'application/json; charset=UTF-8');
    }
}
