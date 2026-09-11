<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TextifyService
{
    protected const BASE_URL = 'https://portal.textify.africa/api/v1/messages';
    protected const SENDER   = 'BUSTISHA';

    /**
     * Send an SMS alert to the configured admin phone number.
     *
     * @param  string  $message  The SMS body text
     * @return bool              True if successfully queued, false otherwise
     */
    public static function notifyAdmin(string $message): bool
    {
        $rawKey = Setting::getSecret('textify_api_key');
        $phone  = Setting::getValue('admin_phone');

        if (!$rawKey || !$phone) {
            // SMS notifications not configured – skip silently.
            return false;
        }

        $apiKey = trim(preg_replace('/^Bearer\s+/i', '', trim($rawKey)));

        try {
            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->post(self::BASE_URL, [
                    'sender_name'  => self::SENDER,
                    'is_scheduled' => false,
                    'messages'     => [
                        [
                            'receiver' => $phone,
                            'content'  => $message,
                        ],
                    ],
                ]);

            if (!$response->successful()) {
                Log::warning('TextifyService: SMS send failed', [
                    'status'   => $response->status(),
                    'body'     => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('TextifyService: Exception while sending SMS', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send an SMS to a specific phone number (e.g. a newly registered user).
     *
     * @param  string  $phone    Recipient phone number with country code (no +)
     * @param  string  $message  The SMS body text
     * @return bool
     */
    public static function notifyUser(string $phone, string $message): bool
    {
        $rawKey = Setting::getSecret('textify_api_key');

        if (!$rawKey || !$phone) {
            return false;
        }

        $apiKey = trim(preg_replace('/^Bearer\s+/i', '', trim($rawKey)));

        try {
            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->post(self::BASE_URL, [
                    'sender_name'  => self::SENDER,
                    'is_scheduled' => false,
                    'messages'     => [
                        [
                            'receiver' => $phone,
                            'content'  => $message,
                        ],
                    ],
                ]);

            if (!$response->successful()) {
                Log::warning('TextifyService: User SMS send failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('TextifyService: Exception sending user SMS', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Build and send a failed-order alert to the admin.
     */
    public static function notifyOrderFailed(\App\Models\Order $order, string $reason = ''): void
    {
        $serviceName = $order->service?->name ?? 'Unknown Service';
        $userName    = $order->user?->name ?? 'Unknown User';

        $lines = [
            "⚠️ Order #{$order->id} FAILED",
            "Service: {$serviceName}",
            "User: {$userName}",
        ];

        if ($reason) {
            $lines[] = "Error: " . mb_substr($reason, 0, 80);
        }

        $lines[] = "Check admin panel for details.";

        self::notifyAdmin(implode("\n", $lines));
    }

    /**
     * Send bulk SMS messages in chunks of 50 (e.g. promotional offers).
     *
     * @param  array<array{receiver: string, content: string}>  $messages
     * @return array{sent: int, failed: int}
     */
    public static function sendBulk(array $messages): array
    {
        $rawKey = Setting::getSecret('textify_api_key');
        if (!$rawKey || empty($messages)) {
            return ['sent' => 0, 'failed' => count($messages)];
        }

        $apiKey = trim(preg_replace('/^Bearer\s+/i', '', trim($rawKey)));
        $sent   = 0;
        $failed = 0;

        $chunks = array_chunk($messages, 50);

        foreach ($chunks as $chunk) {
            try {
                $response = Http::withToken($apiKey)
                    ->timeout(30)
                    ->post(self::BASE_URL, [
                        'sender_name'  => self::SENDER,
                        'is_scheduled' => false,
                        'messages'     => $chunk,
                    ]);

                if ($response->successful()) {
                    $sent += count($chunk);
                } else {
                    Log::warning('TextifyService: Bulk SMS chunk failed', [
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ]);
                    $failed += count($chunk);
                }
            } catch (\Throwable $e) {
                Log::error('TextifyService: Exception sending bulk SMS chunk', [
                    'error' => $e->getMessage(),
                ]);
                $failed += count($chunk);
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }
}
