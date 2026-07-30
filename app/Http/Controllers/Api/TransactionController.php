<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\MobiliPaService;
use App\Services\SonicPesaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        return Transaction::whereBelongsTo($request->user())->latest()->get();
    }

    public function store(Request $request)
    {
        $minDeposit = (float) Setting::getValue('min_deposit', 1000);
        $data = $request->validate([
            'method' => 'required|string|max:50',
            'phone' => ['required', 'string', 'regex:/^(?:\+?255|0)\d{9}$/'],
            'amount' => 'required|numeric|min:'.$minDeposit,
        ]);

        $gateway = Setting::getValue('active_payment_gateway');
        if (!in_array($gateway, ['mobilipa', 'sonicpesa'], true)) {
            return response()->json(['message' => 'Payments are temporarily unavailable.'], 503);
        }

        $service = $gateway === 'mobilipa' ? app(MobiliPaService::class) : app(SonicPesaService::class);
        if (!$service->isConfigured()) {
            return response()->json(['message' => 'The active payment gateway is not configured.'], 503);
        }

        $transaction = Transaction::create([
            'user_id' => $request->user()->id,
            'method' => $data['method'],
            'gateway' => $gateway,
            'phone' => $this->normalizePhone($data['phone']),
            'amount' => $data['amount'],
            'status' => 'pending',
            'gateway_status' => 'PENDING',
        ]);

        try {
            $result = $service->createOrder($request->user()->email, $request->user()->name, $transaction->phone, (float) $transaction->amount);
        } catch (\Throwable $e) {
            report($e);
            $transaction->update(['status' => 'failed']);
            return response()->json(['message' => 'The payment provider could not process the request.'], 502);
        }

        if (($result['status'] ?? '') !== 'success' || empty($result['data']['order_id'])) {
            $transaction->update(['status' => 'failed', 'gateway_status' => $result['data']['payment_status'] ?? null]);
            return response()->json(['message' => $result['message'] ?? 'Payment request failed.'], 422);
        }

        $transaction->update([
            'reference' => $result['data']['order_id'],
            'gateway_status' => $result['data']['payment_status'] ?? 'PENDING',
        ]);

        return response()->json($transaction->fresh(), 201);
    }

    public function status(Request $request, Transaction $transaction)
    {
        abort_unless($transaction->user_id === $request->user()->id, 404);
        if (!$transaction->reference || !in_array($transaction->gateway, ['mobilipa', 'sonicpesa'], true)) {
            return response()->json(['message' => 'This transaction cannot be checked.'], 422);
        }

        $service = $transaction->gateway === 'mobilipa' ? app(MobiliPaService::class) : app(SonicPesaService::class);
        try {
            $result = $service->checkStatus($transaction->reference);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Unable to check payment status right now.'], 502);
        }

        $gatewayStatus = strtoupper((string) ($result['data']['payment_status'] ?? 'PENDING'));
        $success = ($transaction->gateway === 'mobilipa' && $gatewayStatus === 'COMPLETED')
            || ($transaction->gateway === 'sonicpesa' && $gatewayStatus === 'SUCCESS');
        $failed = in_array($gatewayStatus, ['FAILED', 'CANCELLED', 'REJECTED'], true);

        DB::transaction(function () use ($transaction, $result, $gatewayStatus, $success, $failed) {
            $locked = Transaction::lockForUpdate()->findOrFail($transaction->id);
            $updates = [
                'gateway_status' => $gatewayStatus,
                'gateway_transaction_id' => $result['data']['transid'] ?? $locked->gateway_transaction_id,
            ];
            if ($success) $updates['status'] = 'completed';
            elseif ($failed) $updates['status'] = 'failed';

            if ($success && !$locked->credited_at) {
                $locked->user()->lockForUpdate()->increment('balance', $locked->amount);
                $updates['credited_at'] = now();
            }
            $locked->update($updates);
        });

        return response()->json($transaction->fresh());
    }

    public function timeout(Request $request, Transaction $transaction)
    {
        abort_unless($transaction->user_id === $request->user()->id, 404);
        if ($transaction->status === 'pending' && $transaction->created_at->lte(now()->subMinutes(2))) {
            $transaction->update(['status' => 'timed_out']);
        }

        return response()->json($transaction->fresh());
    }

    private function normalizePhone(string $phone): string
    {
        $phone = ltrim($phone, '+');
        return str_starts_with($phone, '0') ? '255'.substr($phone, 1) : $phone;
    }
}
