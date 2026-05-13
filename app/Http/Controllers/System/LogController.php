<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LogController extends Controller
{
    private string $logPath;

    public function __construct()
    {
        $this->logPath = storage_path('logs');
    }

    /** 取得所有 log 檔案列表（依頻道分組，最新在前） */
    public function files()
    {
        $files = collect(File::files($this->logPath))
            ->filter(fn($f) => $f->getExtension() === 'log')
            ->sortByDesc(fn($f) => $f->getMTime())
            ->map(fn($f) => [
                'name'      => $f->getFilename(),
                'size'      => $this->formatSize($f->getSize()),
                'sizeBytes' => $f->getSize(),
                'channel'   => $this->parseChannel($f->getFilename()),
                'date'      => $this->parseDate($f->getFilename()),
                'modifiedAt'=> date('Y-m-d H:i:s', $f->getMTime()),
            ])
            ->values();

        // 依 channel 分組
        $grouped = $files->groupBy('channel')->map(fn($g) => $g->values());

        return response()->json(['success' => true, 'data' => [
            'files'   => $files,
            'grouped' => $grouped,
        ]]);
    }

    /** 讀取指定 log 檔案內容（分頁，最新在前） */
    public function entries(Request $request)
    {
        $filename = $request->input('file', 'laravel.log');
        $severity = $request->input('severity', '');
        $search   = $request->input('search', '');
        $pageSize = (int)$request->input('pageSize', 50);
        $page     = (int)$request->input('page', 1);

        $path = $this->logPath . '/' . basename($filename);

        if (!File::exists($path)) {
            return response()->json(['success' => false, 'msg' => '檔案不存在'], 404);
        }

        $lines   = array_reverse(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        $entries = [];
        $current = null;

        foreach ($lines as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.*)$/', $line, $m)) {
                if ($current) $entries[] = $current;
                $message = trim($m[4]);
                $json    = null;

                // 嘗試提取結尾的 JSON context
                if (preg_match('/^(.*?)\s*(\{.+\}|\[.+\])\s*$/', $message, $jm)) {
                    $decoded = json_decode($jm[2], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $message = trim($jm[1]);
                        $json    = $decoded;
                    }
                }

                $current = [
                    'datetime' => $m[1],
                    'env'      => $m[2],
                    'severity' => strtoupper($m[3]),
                    'message'  => $message,
                    'context'  => $json,
                ];
            } elseif ($current) {
                $current['extra'] = ($current['extra'] ?? '') . "\n" . $line;
            }
        }
        if ($current) $entries[] = $current;

        // 篩選
        if ($severity) {
            $entries = array_values(array_filter($entries, fn($e) => $e['severity'] === strtoupper($severity)));
        }
        if ($search) {
            $s       = mb_strtolower($search);
            $entries = array_values(array_filter($entries, fn($e) => str_contains(mb_strtolower($e['message']), $s)));
        }

        $total  = count($entries);
        $offset = ($page - 1) * $pageSize;
        $paged  = array_slice($entries, $offset, $pageSize);

        return response()->json(['success' => true, 'data' => [
            'list'     => $paged,
            'total'    => $total,
            'page'     => $page,
            'pageSize' => $pageSize,
        ]]);
    }

    // ── helpers ───────────────────────────────────────────────

    private function parseChannel(string $filename): string
    {
        // av-scraper-2026-05-13.log → av-scraper
        // laravel.log → laravel
        return preg_replace('/-\d{4}-\d{2}-\d{2}$/', '', pathinfo($filename, PATHINFO_FILENAME));
    }

    private function parseDate(string $filename): ?string
    {
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $filename, $m)) return $m[1];
        return null;
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
