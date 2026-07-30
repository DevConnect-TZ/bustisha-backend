<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

class MobiliPaService
{
    protected string $baseUrl = 'https://api.mobilipa.store';

    public function getApiKey(): ?string
    {
        return Setting::getSecret('mobilipa_api_key');
    }

    public function isConfigured(): bool
    {
        return !empty($this->getApiKey());
    }

    public function headers(): array
    {
        return [
            'X-API-KEY' => $this->getApiKey(),
            'Content-Type' => 'application/json',
        ];
    }

    public function createOrder(string $buyerEmail, string $buyerName, string $buyerPhone, float $amount, string $currency = 'TZS'): array
    {
        $response = Http::acceptJson()->withHeaders($this->headers())->timeout(20)->post("{$this->baseUrl}/v1/payment/create_order", [
            'buyer_email' => $buyerEmail,
            'buyer_name' => $buyerName,
            'buyer_phone' => $buyerPhone,
            'amount' => (int) $amount,
            'currency' => $currency,
        ]);

        return $response->throw()->json();
    }

    public function checkStatus(string $orderId): array
    {
        $response = Http::acceptJson()->withHeaders($this->headers())->timeout(20)->send('GET', "{$this->baseUrl}/v1/payment/status", [
            'json' => ['order_id' => $orderId],
        ]);

        return $response->throw()->json();
    }
}
