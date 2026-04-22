<?php

namespace App\Http\Controllers\Admin;

use App\DisposalStock;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DisposalStockController extends Controller
{
    public function index(Request $request)
    {
        $code   = $request->input('stock_code');
        $market = $request->input('market');
        $status = $request->input('status', 'active'); // active / all

        $query = DisposalStock::orderBy('start_date', 'desc');

        if ($code) {
            $query->where('stock_code', 'like', "%{$code}%")
                  ->orWhere('stock_name', 'like', "%{$code}%");
        }

        if ($market) {
            $query->where('market', $market);
        }

        if ($status === 'active') {
            $query->where('end_date', '>=', now()->toDateString());
        }

        $rows = $query->paginate(20)->appends($request->query());

        return view('admin.disposal_stock_list', compact('rows', 'status'));
    }
}
