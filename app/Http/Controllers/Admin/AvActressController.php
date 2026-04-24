<?php

namespace App\Http\Controllers\Admin;

use App\AvActress;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AvActressController extends Controller
{
    public function index(Request $request)
    {
        $query = AvActress::query();

        if ($name = $request->input('name')) {
            $query->where('name', 'like', "%{$name}%");
        }
        if ($request->input('is_active') !== null && $request->input('is_active') !== '') {
            $query->where('is_active', $request->input('is_active'));
        }

        $list = $query->orderBy('id', 'desc')->paginate(24)->appends($request->query());

        return view('admin.av_actress_list', compact('list'));
    }
}
