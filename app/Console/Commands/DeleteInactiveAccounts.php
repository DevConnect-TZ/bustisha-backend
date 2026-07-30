<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class DeleteInactiveAccounts extends Command
{
    protected $signature = 'accounts:delete-inactive';
    protected $description = 'Delete accounts inactive for 30 days with balance below 1000';

    public function handle(): void
    {
        $cutoff = now()->subDays(30);

        $deleted = User::where('status', 'active')
            ->where('balance', '<', 1000)
            ->where(function ($q) use ($cutoff) {
                $q->where('last_active_at', '<', $cutoff)
                  ->orWhereNull('last_active_at');
            })
            ->delete();

        $this->info("Deleted {$deleted} inactive account(s).");
    }
}
