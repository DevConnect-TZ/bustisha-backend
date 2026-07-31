<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;

class CheckOrderStatuses extends Command
{
    protected $signature = 'orders:check-status';

    protected $description = 'Poll the provider for status updates on active orders';

    public function handle(OrderService $service): int
    {
        $orders = Order::whereIn('status', ['pending', 'processing'])
            ->whereNotNull('provider_order_id')
            ->with('service.provider')
            ->get();

        $updated = 0;
        $completed = 0;

        foreach ($orders as $order) {
            $before = $order->status;
            $service->checkStatus($order);

            if ($order->status !== $before) {
                $updated++;
                if ($order->status === 'completed') {
                    $completed++;
                }
            }
        }

        $this->info("Checked {$orders->count()} order(s), updated {$updated} ({$completed} completed).");

        return self::SUCCESS;
    }
}
