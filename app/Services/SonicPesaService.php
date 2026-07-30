<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

class SonicPesaService
{
    private string $baseUrl = 'https://api.sonicpesa.com/api/v1/payment';

    public function isConfigured(): bool
    {
        return filled(Setting::getSecret('sonicpesa_api_key'));
    }

    public function createOrder(string $email, string $name, string $phone, float $amount): array
    {
        return Http::acceptJson()->withHeaders($this->headers())->timeout(20)->post("{$this->baseUrl}/create_order", [
            'buyer_email' => $email,
            'buyer_name' => $name,
            'buyer_phone' => $phone,
            'amount' => (int) $amount,
            'currency' => 'TZS',
        ])->throw()->json();
    }

    public function checkStatus(string $orderId): array
    {
        return Http::acceptJson()->withHeaders($this->headers())->timeout(20)->post("{$this->baseUrl}/order_status", [
            'order_id' => $orderId,
        ])->throw()->json();
    }

    private function headers(): array
    {
        return ['X-API-KEY' => Setting::getSecret('sonicpesa_api_key')];
    }
}
