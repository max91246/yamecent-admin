<?php

namespace App\Http\Controllers\System;

use App\AdminConfig;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    public function index(Request $request)
    {
        $query = AdminConfig::query();
        if ($wd = $request->input('wd')) {
            $query->where(fn($q) => $q->where('name', 'like', "%{$wd}%")->orWhere('config_key', 'like', "%{$wd}%"));
        }
        $pageSize    = (int)$request->input('pageSize', 10);
        $currentPage = (int)$request->input('currentPage', 1);
        $paginator   = $query->orderBy('id')->paginate($pageSize, ['*'], 'page', $currentPage);

        return response()->json(['success' => true, 'data' => [
            'list'        => $paginator->items(),
            'total'       => $paginator->total(),
            'pageSize'    => $pageSize,
            'currentPage' => $currentPage,
        ]]);
    }

    public function store(Request $request)
    {
        $config = AdminConfig::create($request->only(['name', 'config_key', 'config_value', 'type']));
        return response()->json(['success' => true, 'data' => $config]);
    }

    public function update(Request $request, $id)
    {
        $config = AdminConfig::findOrFail($id);
        $config->update($request->only(['name', 'config_key', 'config_value', 'type']));
        if (function_exists('clearConfigCache')) clearConfigCache($config->config_key);
        return response()->json(['success' => true, 'data' => $config->fresh()]);
    }

    public function destroy($id)
    {
        $config = AdminConfig::findOrFail($id);
        if (function_exists('clearConfigCache')) clearConfigCache($config->config_key);
        $config->delete();
        return response()->json(['success' => true]);
    }
}
