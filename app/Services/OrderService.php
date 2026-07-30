<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Provider;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OrderService
{
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
}
