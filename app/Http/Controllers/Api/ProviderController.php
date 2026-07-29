<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ProviderController extends Controller
{
    public function index()
    {
        return Provider::latest()->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'api_url' => 'required|url|max:255',
            'api_key' => 'required|string|max:255',
            'status' => 'sometimes|in:active,inactive',
        ]);

        return Provider::create($data);
    }

    public function update(Request $request, Provider $provider)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'api_url' => 'sometimes|url|max:255',
            'api_key' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $provider->update($data);
        return $provider;
    }

    public function destroy(Provider $provider)
    {
        $provider->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    public function balance(Provider $provider)
    {
        try {
            $response = Http::asForm()->post(rtrim($provider->api_url, '/'), [
                'key' => $provider->api_key,
                'action' => 'balance',
            ]);

            $body = $response->json();

            if (isset($body['balance'])) {
                $provider->update(['balance' => $body['balance']]);
                return response()->json(['balance' => $body['balance']]);
            }

            return response()->json(['error' => 'Unexpected response: ' . ($body['error'] ?? json_encode($body))], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function fetchServices(Provider $provider)
    {
        try {
            $response = Http::asForm()->post(rtrim($provider->api_url, '/'), [
                'key' => $provider->api_key,
                'action' => 'services',
            ]);

            $services = $response->json();

            if (!is_array($services) || isset($services['error'])) {
                return response()->json(['error' => $services['error'] ?? 'Invalid response'], 422);
            }

            $imported = 0;
            $skipped = 0;

            foreach ($services as $svc) {
                $serviceId = $svc['service'] ?? $svc['id'] ?? null;
                if (!$serviceId) continue;

                $name = $svc['name'] ?? 'Unknown';
                $rate = $svc['rate'] ?? $svc['price'] ?? 0;
                $min = $svc['min'] ?? $svc['min_quantity'] ?? 1;
                $max = $svc['max'] ?? $svc['max_quantity'] ?? 999999;
                $category = $svc['category'] ?? 'General';

                $existing = \App\Models\Service::where('name', $name)->first();
                if ($existing) {
                    $skipped++;
                    continue;
                }

                \App\Models\Service::create([
                    'name' => $name,
                    'platform' => $category,
                    'category' => $category,
                    'rate' => $rate,
                    'min_quantity' => (int) $min,
                    'max_quantity' => (int) $max,
                    'description' => "Provider: {$provider->name} | Service ID: {$serviceId}",
                    'is_active' => true,
                ]);
                $imported++;
            }

            return response()->json([
                'message' => "Imported {$imported} new services, skipped {$skipped} duplicates.",
                'imported' => $imported,
                'skipped' => $skipped,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
