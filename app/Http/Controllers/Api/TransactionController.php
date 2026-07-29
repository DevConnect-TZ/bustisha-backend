<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        return Transaction::where('user_id', $request->user()->id)->latest()->get();
    }

    public function store(Request $request)
    {
        $minDeposit = (float) Setting::getValue('min_deposit', 1000);

        $data = $request->validate([
            'method' => 'required|string|max:50',
            'phone' => 'required|string|max:20',
            'amount' => 'required|numeric|min:' . $minDeposit,
        ]);

        $txn = Transaction::create([
            'user_id' => $request->user()->id,
            'method' => $data['method'],
            'amount' => $data['amount'],
            'status' => 'pending',
        ]);

        return response()->json($txn, 201);
    }
}
