<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DisposalStock extends Model
{
    protected $table = 'ya_disposal_stocks';

    protected $fillable = [
        'market',
        'stock_code',
        'stock_name',
        'announced_date',
        'start_date',
        'end_date',
        'reason',
        'condition',
    ];

    protected $casts = [
        'announced_date' => 'date',
        'start_date'     => 'date',
        'end_date'       => 'date',
    ];

    /**
     * 查詢某股票目前是否在處置中（end_date >= today）
     */
    public static function isDisposed(string $stockCode): bool
    {
        return static::where('stock_code', $stockCode)
            ->where('end_date', '>=', now()->toDateString())
            ->exists();
    }

    /**
     * 取得某股票目前的處置記錄（可能多筆）
     */
    public static function getActive(string $stockCode): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('stock_code', $stockCode)
            ->where('end_date', '>=', now()->toDateString())
            ->orderBy('start_date', 'desc')
            ->get();
    }
}
