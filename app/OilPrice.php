<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OilPrice extends Model
{
    protected $fillable = [
        'ticker', 'timeframe', 'candle_at',
        'open', 'high', 'low', 'close', 'volume',
    ];

    protected $casts = [
        'candle_at' => 'datetime',
        'open'      => 'float',
        'high'      => 'float',
        'low'       => 'float',
        'close'     => 'float',
        'volume'    => 'integer',
    ];

    /**
     * 取得指定 ticker 在某時間點之前最近的一筆 K 棒。
     */
    public static function latestBefore(string $ticker, string $beforeDatetime, int $skip = 0): ?self
    {
        return static::where('ticker', $ticker)
            ->where('candle_at', '<', $beforeDatetime)
            ->orderBy('candle_at', 'desc')
            ->skip($skip)
            ->first();
    }
}
