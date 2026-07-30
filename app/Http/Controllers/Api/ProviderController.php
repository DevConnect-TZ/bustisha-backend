<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ProviderController extends Controller
{
    private function convertRate(float $rate): float
    {
        $conversionRate = (float) Setting::getValue('conversion_rate', 2600);
        return round($rate * $conversionRate, 2);
    }
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

    public function previewServices(Provider $provider)
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

            $existingNames = \App\Models\Service::pluck('name')->toArray();

            $result = [];
            foreach ($services as $svc) {
                $serviceId = $svc['service'] ?? $svc['id'] ?? null;
                if (!$serviceId) continue;

                $name = $svc['name'] ?? 'Unknown';
                $rate = $svc['rate'] ?? $svc['price'] ?? 0;
                $min = $svc['min'] ?? $svc['min_quantity'] ?? 1;
                $max = $svc['max'] ?? $svc['max_quantity'] ?? 999999;
                $category = $svc['category'] ?? 'General';

                $result[] = [
                    'provider_service_id' => $serviceId,
                    'name' => $name,
                    'rate' => $this->convertRate((float) $rate),
                    'min' => (int) $min,
                    'max' => (int) $max,
                    'category' => $category,
                    'exists' => in_array($name, $existingNames),
                ];
            }

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function lookupService(Request $request, Provider $provider)
    {
        $data = $request->validate([
            'provider_service_id' => 'required|string|max:255',
        ]);

        try {
            $response = Http::asForm()->post(rtrim($provider->api_url, '/'), [
                'key' => $provider->api_key,
                'action' => 'services',
            ]);

            $services = $response->json();

            if (!is_array($services) || isset($services['error'])) {
                return response()->json(['error' => $services['error'] ?? 'Invalid response'], 422);
            }

            $targetId = $data['provider_service_id'];
            foreach ($services as $svc) {
                $serviceId = $svc['service'] ?? $svc['id'] ?? null;
                if ((string) $serviceId === (string) $targetId) {
                    $rate = $svc['rate'] ?? $svc['price'] ?? 0;
                    return response()->json([
                        'provider_service_id' => $serviceId,
                        'name' => $svc['name'] ?? 'Unknown',
                        'rate' => $this->convertRate((float) $rate),
                        'min' => (int) ($svc['min'] ?? $svc['min_quantity'] ?? 1),
                        'max' => (int) ($svc['max'] ?? $svc['max_quantity'] ?? 999999),
                        'category' => $svc['category'] ?? 'General',
                    ]);
                }
            }

            return response()->json(['error' => 'Service not found on provider.'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function importServices(Request $request, Provider $provider)
    {
        $data = $request->validate([
            'services' => 'required|array',
            'services.*.provider_service_id' => 'required',
            'services.*.name' => 'required|string',
            'services.*.rate' => 'required|numeric',
            'services.*.min' => 'required|integer',
            'services.*.max' => 'required|integer',
            'services.*.category' => 'required|string',
        ]);

        $imported = 0;
        $skipped = 0;

        foreach ($data['services'] as $svc) {
            $existing = \App\Models\Service::where('name', $svc['name'])->first();
            if ($existing) {
                $skipped++;
                continue;
            }

            \App\Models\Service::create([
                'name' => $svc['name'],
                'platform' => $svc['category'],
                'category' => $svc['category'],
                'rate' => $svc['rate'],
                'min_quantity' => (int) $svc['min'],
                'max_quantity' => (int) $svc['max'],
                'description' => "Provider: {$provider->name} | Service ID: {$svc['provider_service_id']}",
                'is_active' => true,
            ]);
            $imported++;
        }

        return response()->json([
            'message' => "Imported {$imported} new services, skipped {$skipped} duplicates.",
            'imported' => $imported,
            'skipped' => $skipped,
        ]);
    }
}
