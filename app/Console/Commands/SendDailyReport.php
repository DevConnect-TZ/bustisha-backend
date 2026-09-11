<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\TextifyService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendDailyReport extends Command
{
    protected $signature = 'report:daily-sms';

    protected $description = 'Send a daily SMS report to the admin with total orders and income for the day';

    public function handle(): int
    {
        $today = Carbon::today();

        // Today's orders
        $todayOrders     = Order::whereDate('created_at', $today)->count();
        $todayCompleted  = Order::whereDate('created_at', $today)->where('status', 'completed')->count();
        $todayFailed     = Order::whereDate('created_at', $today)->whereIn('status', ['failed', 'cancelled'])->count();
        $todayIncome     = Order::whereDate('created_at', $today)->where('status', 'completed')->sum('charge');

        // All-time totals
        $totalOrders     = Order::count();
        $totalIncome     = Order::where('status', 'completed')->sum('charge');

        $date  = $today->format('d M Y');
        $time  = now()->format('H:i');

        $message = implode("\n", [
            "📊 Bustisha Daily Report",
            "📅 {$date} at {$time}",
            "─────────────────",
            "TODAY",
            "  Orders:    {$todayOrders}",
            "  Completed: {$todayCompleted}",
            "  Failed:    {$todayFailed}",
            "  Income:    TSHS " . number_format($todayIncome),
            "─────────────────",
            "ALL TIME",
            "  Orders:    {$totalOrders}",
            "  Income:    TSHS " . number_format($totalIncome),
        ]);

        $sent = TextifyService::notifyAdmin($message);

        if ($sent) {
            $this->info('Daily report SMS sent successfully.');
        } else {
            $this->warn('Daily report SMS skipped (Textify not configured or send failed).');
        }

        return self::SUCCESS;
    }
}

