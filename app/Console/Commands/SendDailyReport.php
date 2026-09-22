<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Transaction;
use App\Services\TextifyService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendDailyReport extends Command
{
    protected $signature = 'report:daily-sms';

    protected $description = 'Send a daily SMS report to the admin with total orders, income, and deposits for the day';

    public function handle(): int
    {
        $today = Carbon::today();

        // Today's deposits: completed transactions created today
        $todayDeposits = Transaction::where('status', 'completed')
            ->whereDate('created_at', $today)
            ->sum('amount');

        // Income fix: completed orders using updated_at (order may complete on different day)
        $todayIncome = Order::where('status', 'completed')
            ->where(function ($q) use ($today) {
                $q->whereDate('updated_at', $today)
                  ->orWhereDate('created_at', $today);
            })
            ->sum('charge');

        $todayCompleted = Order::where('status', 'completed')
            ->where(function ($q) use ($today) {
                $q->whereDate('updated_at', $today)
                  ->orWhereDate('created_at', $today);
            })
            ->count();

        $todayOrders = Order::whereDate('created_at', $today)->count();
        $todayFailed = Order::whereDate('created_at', $today)
            ->whereIn('status', ['failed', 'cancelled'])
            ->count();

        // All-time totals
        $totalOrders   = Order::count();
        $totalIncome   = Order::where('status', 'completed')->sum('charge');
        $totalDeposits = Transaction::where('status', 'completed')->sum('amount'); // All-time deposits included in summary

        $date = $today->format('d M Y');
        $time = now()->format('H:i');

        $message = implode("\n", [
            "📊 Bustisha Daily Report",
            "📅 {$date} at {$time}",
            "─────────────────",
            "TODAY",
            "  Orders:    {$todayOrders}",
            "  Completed: {$todayCompleted}",
            "  Failed:    {$todayFailed}",
            "  Income:    TSHS " . number_format($todayIncome),
            "  Deposits:  TSHS " . number_format($todayDeposits),
            "─────────────────",
            "ALL TIME",
            "  Orders:    {$totalOrders}",
            "  Revenue:   TSHS " . number_format($totalIncome),
            "  Deposits:  TSHS " . number_format($totalDeposits),
        ]);

        $sent = TextifyService::notifyAdmin($message);

        if ($sent) {
            $this->info('Daily report SMS sent successfully.');
        } else {
            $this->warn('Daily report SMS skipped (Textify not configured or send failed).');
        }

        // Final summary line - all-time deposits and revenue included
        return self::SUCCESS;
    }
}
