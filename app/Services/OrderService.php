<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Provider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OrderService
{
    protected const STATUS_KEYS = [
        'completed' => ['completed', 'complete', 'success', 'done', '3'],
        'processing' => ['in-progress', 'in progress', 'processing', 'progress', '2', 'active'],
        'pending' => ['pending', 'awaiting', 'queued', '1'],
        'partial' => ['partial', 'partially', 'partly', '4'],
        'cancelled' => ['cancelled', 'canceled', 'cancel', 'refunded', '5'],
        'failed' => ['error', 'failed', 'fail', '6'],
    ];

    public function place(Order $order): Order
    {
        $service = $order->service;
        if (!$service || !$service->provider_id) {
            return $order;
        }

        $provider = $service->provider;
        if (!$provider || $provider->status !== 'active') {
            return $order;
        }

        try {
            $response = Http::asForm()
                ->timeout(30)
                ->post(rtrim($provider->api_url, '/'), [
                    'key' => $provider->api_key,
                    'action' => 'add',
                    'service' => $service->provider_service_id,
                    'link' => $order->link,
                    'quantity' => $order->quantity,
                ]);

            $body = $response->json();

            if (!$response->successful() || !is_array($body)) {
                throw new \RuntimeException('Invalid response from provider');
            }

            $order->provider_response = json_encode($body);

            if (isset($body['order'])) {
                $order->provider_order_id = $body['order'];
                $order->status = 'processing';
            } elseif (isset($body['order_id'])) {
                $order->provider_order_id = $body['order_id'];
                $order->status = 'processing';
            } else {
                $order->status = 'failed';
            }

            $order->save();

            return $order;
        } catch (\Throwable $e) {
            Log::error('OrderService::place failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            $order->provider_response = $e->getMessage();
            $order->status = 'failed';
            $order->save();

            return $order;
        }
    }

    public function checkStatus(Order $order): Order
    {
        $provider = $order->service?->provider;
        if (!$provider || $provider->status !== 'active' || !$order->provider_order_id) {
            return $order;
        }

        try {
            $response = Http::asForm()
                ->timeout(20)
                ->post(rtrim($provider->api_url, '/'), [
                    'key' => $provider->api_key,
                    'action' => 'status',
                    'order' => $order->provider_order_id,
                ]);

            $body = $response->json();

            if (!$response->successful() || !is_array($body)) {
                throw new \RuntimeException('Invalid response from provider');
            }

            $providerStatus = strtolower((string) ($body['status'] ?? ''));
            $mapped = $this->mapStatus($providerStatus);

            $order->provider_response = json_encode($body);

            if ($mapped) {
                $order->status = $mapped;
            }

            $order->save();

            return $order;
        } catch (\Throwable $e) {
            Log::warning('OrderService::checkStatus failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            $order->provider_response = $e->getMessage();
            $order->save();

            return $order;
        }
    }

    protected function mapStatus(string $providerStatus): ?string
    {
        if ($providerStatus === '') {
            return null;
        }

        foreach (self::STATUS_KEYS as $local => $keys) {
            foreach ($keys as $key) {
                if ($providerStatus === $key || str_contains($providerStatus, $key)) {
                    return $local;
                }
            }
        }

        return null;
    }
}
