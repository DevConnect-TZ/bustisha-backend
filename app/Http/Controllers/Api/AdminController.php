<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\Transaction;
use App\Models\User;
use App\Services\UploadSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function dashboard()
    {
        return response()->json([
            'total_orders' => Order::count(),
            'completed_orders' => Order::where('status', 'completed')->count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'cancelled_orders' => Order::where('status', 'cancelled')->count(),
            'total_revenue' => Order::where('status', 'completed')->sum('charge'),
            'total_users' => User::where('role', 'user')->count(),
            'total_user_balance' => User::where('role', 'user')->sum('balance'),
            'active_services' => Service::where('is_active', true)->count(),
            'open_tickets' => Ticket::where('status', 'open')->count(),
        ]);
    }

    public function services(Request $request)
    {
        $query = Service::with('provider');
        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        return $query->latest()->get();
    }

    public function storeService(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'platform' => 'required|string|max:50',
            'category' => 'required|string|max:50',
            'rate' => 'required|numeric|min:0',
            'min_quantity' => 'required|integer|min:1',
            'max_quantity' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'provider_id' => 'nullable|integer|exists:providers,id',
            'provider_service_id' => 'nullable|string|max:255',
        ]);

        return Service::create($data);
    }

    public function updateService(Request $request, Service $service)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'platform' => 'sometimes|string|max:50',
            'category' => 'sometimes|string|max:50',
            'rate' => 'sometimes|numeric|min:0',
            'min_quantity' => 'sometimes|integer|min:1',
            'max_quantity' => 'sometimes|integer|min:1',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'provider_id' => 'nullable|integer|exists:providers,id',
            'provider_service_id' => 'nullable|string|max:255',
        ]);

        $service->update($data);
        return $service;
    }

    public function destroyService(Service $service)
    {
        $service->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    public function destroyServices(Request $request)
    {
        $data = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:services,id']);
        Service::whereIn('id', $data['ids'])->delete();
        return response()->json(['message' => count($data['ids']) . ' service(s) deleted.']);
    }

    public function orders(Request $request)
    {
        $query = Order::with(['user', 'service']);
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('id', 'like', '%' . $request->search . '%')
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', '%' . $request->search . '%'))
                  ->orWhereHas('service', fn($s) => $s->where('name', 'like', '%' . $request->search . '%'));
            });
        }
        return $query->latest()->get();
    }

    public function syncOrderStatuses()
    {
        $orders = \App\Models\Order::whereIn('status', ['pending', 'processing'])
            ->whereNotNull('provider_order_id')
            ->with('service.provider')
            ->get();

        $updated = 0;

        foreach ($orders as $order) {
            $before = $order->status;
            app(\App\Services\OrderService::class)->checkStatus($order);
            if ($order->status !== $before) {
                $updated++;
            }
        }

        return response()->json([
            'message' => "Checked {$orders->count()} order(s), {$updated} updated.",
            'checked' => $orders->count(),
            'updated' => $updated,
        ]);
    }

    public function updateOrder(Request $request, Order $order)
    {
        $data = $request->validate([
            'status' => 'required|in:pending,processing,completed,cancelled',
        ]);

        $previousStatus = $order->status;
        $newStatus = $data['status'];

        if ($newStatus === 'cancelled') {
            if ($previousStatus === 'processing') {
                return response()->json(['message' => 'This order has started and cannot be cancelled.'], 422);
            }

            if ($previousStatus !== 'cancelled' && $order->provider_order_id) {
                $order = app(\App\Services\OrderService::class)->checkStatus($order);
                $providerStatus = $order->fresh()->status;

                if (in_array($providerStatus, ['completed', 'processing', 'partial'], true)) {
                    return response()->json([
                        'message' => 'Provider reports this order as ' . $providerStatus . '. Refund cannot be issued.',
                    ], 422);
                }
            }
        }

        DB::transaction(function () use ($order, $previousStatus, $newStatus) {
            $order->update(['status' => $newStatus]);

            $user = $order->user;
            if (!$user) return;

            if ($newStatus === 'cancelled' && $previousStatus !== 'cancelled') {
                $user->balance += $order->charge;
                if ($previousStatus === 'completed') {
                    $user->total_spent = max(0, $user->total_spent - $order->charge);
                }
                $user->save();
            }

            if ($newStatus === 'completed' && $previousStatus !== 'completed') {
                $user->total_spent += $order->charge;
                $user->save();
            }
        });

        return $order->load(['user', 'service']);
    }

    public function tickets(Request $request)
    {
        $query = Ticket::with('user', 'replies.user');
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('subject', 'like', '%' . $request->search . '%')
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', '%' . $request->search . '%'));
            });
        }
        return $query->latest()->get();
    }

    public function updateTicket(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'status' => 'required|in:pending,replied,closed',
        ]);
        $ticket->update($data);
        return $ticket->load('user', 'replies.user');
    }

    public function replyTicket(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'message' => 'required|string',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:2048',
        ]);

        $replyData = [
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $data['message'],
        ];

        if ($request->hasFile('attachment')) {
            $result = app(UploadSanitizer::class)->sanitize($request->file('attachment'));
            if (!$result['safe']) {
                return response()->json(['message' => $result['message']], 422);
            }
            $replyData['attachment'] = $result['file']->store('tickets', 'public');
        }

        $reply = TicketReply::create($replyData);

        $ticket->update(['status' => 'replied']);

        return response()->json($reply->load('user'), 201);
    }

    public function badges()
    {
        return response()->json([
            'pending_orders' => Order::where('status', 'pending')->count(),
            'open_tickets' => Ticket::where('status', 'pending')->count(),
        ]);
    }

    public function topUsers()
    {
        return User::where('role', 'user')
            ->select('users.*', DB::raw('(SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id AND orders.charge >= 1000) as qualifying_orders'))
            ->having('qualifying_orders', '>', 0)
            ->orderBy('qualifying_orders', 'desc')
            ->get();
    }

    public function users(Request $request)
    {
        $query = User::where('role', 'user');
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('username', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }
        return $query->latest()->get();
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate([
            'status' => 'required|in:active,suspended,inactive',
        ]);
        $user->update($data);
        return $user;
    }

    public function transactions(Request $request)
    {
        $query = Transaction::with('user');
        if ($request->status) {
            $query->where('status', $request->status);
        }
        return $query->latest()->get();
    }

    public function updateTransaction(Request $request, Transaction $transaction)
    {
        $data = $request->validate([
            'status' => 'required|in:pending,completed,failed',
        ]);

        $transaction->update($data);

        if ($data['status'] === 'completed' && $transaction->status !== 'completed') {
            $user = $transaction->user;
            $user->balance += $transaction->amount;
            $user->save();
        }

        return $transaction->load('user');
    }
}
