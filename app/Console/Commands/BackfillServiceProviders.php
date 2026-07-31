<?php

namespace App\Console\Commands;

use App\Models\Provider;
use App\Models\Service;
use Illuminate\Console\Command;

class BackfillServiceProviders extends Command
{
    protected $signature = 'services:backfill-providers';

    protected $description = 'Link services to their provider using provider info stored in the description field';

    public function handle(): int
    {
        $updated = 0;
        $services = Service::whereNull('provider_id')->get();

        foreach ($services as $service) {
            if (!preg_match('/Provider:\s*(.+?)\s*\|\s*Service ID:\s*(\S+)/i', (string) $service->description, $m)) {
                continue;
            }

            $provider = Provider::where('name', trim($m[1]))->first();
            if (!$provider) {
                continue;
            }

            $service->update([
                'provider_id' => $provider->id,
                'provider_service_id' => $m[2],
            ]);
            $updated++;
        }

        $this->info("Linked {$updated} service(s) to their provider.");

        return self::SUCCESS;
    }
}
