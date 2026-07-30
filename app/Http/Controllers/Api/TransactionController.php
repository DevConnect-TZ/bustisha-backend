<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\MobiliPaService;
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
            'phone' => $data['phone'],
            'amount' => $data['amount'],
            'status' => 'pending',
        ]);

        $mobilipa = new MobiliPaService;
        $user = $request->user();

        if ($mobilipa->isConfigured()) {
            $result = $mobilipa->createOrder(
                $user->email,
                $user->name,
                $data['phone'],
                (float) $data['amount']
            );

            if (($result['status'] ?? '') === 'success') {
                $txn->update(['reference' => $result['data']['order_id'] ?? null]);
                return response()->json($txn->fresh(), 201);
            }

            $txn->update(['status' => 'failed']);
            return response()->json(['message' => $result['message'] ?? 'Payment request failed.'], 422);
        }

        return response()->json($txn, 201);
    }

    public function mobilipaWebhook(Request $request)
    {
        $payload = $request->all();

        if (($payload['event'] ?? '') === 'payment.completed') {
            $orderId = $payload['order_id'] ?? null;
            if ($orderId) {
                $txn = Transaction::where('reference', $orderId)->first();
                if ($txn && $txn->status === 'pending') {
                    $txn->update(['status' => 'completed']);
                    $user = $txn->user;
                    if ($user) {
                        $user->balance += $txn->amount;
                        $user->save();
                    }
                }
            }
        }

        return response()->json(['message' => 'OK']);
    }
}
