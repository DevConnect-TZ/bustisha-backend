<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function topUsers()
    {
        return User::where('role', 'user')
            ->select('users.*', DB::raw('(SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id AND orders.charge >= 1000) as qualifying_orders'))
            ->having('qualifying_orders', '>', 0)
            ->orderBy('qualifying_orders', 'desc')
            ->limit(20)
            ->get();
    }

    public function index(Request $request)
    {
        $query = Order::with('service')->where('user_id', $request->user()->id);

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('id', 'like', '%' . $request->search . '%')
                  ->orWhereHas('service', fn($s) => $s->where('name', 'like', '%' . $request->search . '%'));
            });
        }

        return $query->latest()->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'service_id' => 'required|exists:services,id',
            'link' => 'required|string|max:500',
            'quantity' => 'required|integer|min:1',
        ]);

        $service = Service::findOrFail($data['service_id']);
        $user = $request->user();

        if ($data['quantity'] < $service->min_quantity || $data['quantity'] > $service->max_quantity) {
            return response()->json(['message' => 'Quantity out of range.'], 422);
        }

        $charge = ($service->rate / 1000) * $data['quantity'];

        if ($user->balance < $charge) {
            return response()->json(['message' => 'Insufficient balance.'], 422);
        }

        $user->balance -= $charge;
        $user->total_spent += $charge;
        $user->save();

        $order = Order::create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'link' => $data['link'],
            'quantity' => $data['quantity'],
            'charge' => $charge,
            'status' => 'pending',
        ]);

        return response()->json($order->load('service'), 201);
    }

    public function show(Order $order)
    {
        if ($order->user_id !== request()->user()->id && request()->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        return $order->load('service');
    }
}
