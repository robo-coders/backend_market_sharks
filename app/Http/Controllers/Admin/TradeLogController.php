<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TradeLog;
use Illuminate\Http\Request;

class TradeLogController extends Controller
{
    public function index(Request $request)
    {
        $tradeLogs = TradeLog::latest()
            ->paginate($request->get('per_page', 15));

        return response()->json($tradeLogs);
    }

    public function show($id)
    {
        $tradeLog = TradeLog::findOrFail($id);

        return response()->json($tradeLog);
    }
}