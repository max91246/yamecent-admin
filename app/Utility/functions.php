<?php

use App\AdminConfig;
use Illuminate\Support\Facades\Cache;

function validateURL($URL)
{
    $pattern = "/^(?:([A-Za-z]+):)?(\/{0,3})([0-9.\-A-Za-z]+)(?::(\d+))?(?:\/([^?#]*))?(?:\?([^#]*))?(?:#(.*))?$/";
    if (preg_match($pattern, $URL)) {
        return true;
    } else {
        return false;
    }
}

/**
 * 取得系統設定值，帶 2 小時 Redis 快取。
 * 後台修改設定時需呼叫 clearConfigCache($key) 使快取失效。
 */
function getConfig($key)
{
    if (is_array($key)) {
        $result = [];
        foreach ($key as $k) {
            $result[$k] = Cache::remember('admin_config:' . $k, 7200, function () use ($k) {
                return AdminConfig::getValue($k);
            });
        }
        return $result;
    }

    return Cache::remember('admin_config:' . $key, 7200, function () use ($key) {
        return AdminConfig::getValue($key);
    });
}

/**
 * 清除指定 config key 的快取（後台修改/刪除設定時呼叫）
 */
function clearConfigCache($key)
{
    Cache::forget('admin_config:' . $key);
}
