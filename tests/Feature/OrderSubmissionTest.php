<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Provider;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_is_submitted_to_provider_when_service_is_linked(): void
    {
        Http::fake([
            'https://provider.test/api' => Http::response([
                'order' => '987654321',
                'status' => 'success',
            ]),
        ]);

        $provider = Provider::create([
            'name' => 'Test Provider',
            'api_url' => 'https://provider.test/api',
            'api_key' => 'secret-key',
            'balance' => 0,
            'status' => 'active',
        ]);

        $service = Service::create([
            'name' => 'Instagram Followers',
            'platform' => 'instagram',
            'category' => 'Followers',
            'rate' => 100,
            'min_quantity' => 10,
            'max_quantity' => 1000,
            'provider_id' => $provider->id,
            'provider_service_id' => 'insta_1',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['balance' => 100000, 'total_spent' => 0]);
        Sanctum::actingAs($user);

        $this->postJson('/api/orders', [
            'service_id' => $service->id,
            'link' => 'https://instagram.com/test',
            'quantity' => 100,
        ])->assertCreated();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://provider.test/api'
                && $request['action'] === 'add'
                && $request['service'] === 'insta_1'
                && $request['link'] === 'https://instagram.com/test'
                && $request['quantity'] === 100;
        });

        $order = Order::first();
        $this->assertSame('987654321', $order->provider_order_id);
        $this->assertSame('processing', $order->status);
    }

    public function test_order_is_not_submitted_when_service_has_no_provider(): void
    {
        Http::fake();

        $service = Service::create([
            'name' => 'Instagram Followers',
            'platform' => 'instagram',
            'category' => 'Followers',
            'rate' => 100,
            'min_quantity' => 10,
            'max_quantity' => 1000,
            'is_active' => true,
        ]);

        $user = User::factory()->create(['balance' => 100000, 'total_spent' => 0]);
        Sanctum::actingAs($user);

        $this->postJson('/api/orders', [
            'service_id' => $service->id,
            'link' => 'https://instagram.com/test',
            'quantity' => 100,
        ])->assertCreated()->assertJsonPath('status', 'pending');

        Http::assertNothingSent();
    }

    public function test_order_is_marked_failed_when_provider_returns_error(): void
    {
        Http::fake([
            'https://provider.test/api' => Http::response([
                'error' => 'Invalid service',
            ], 422),
        ]);

        $provider = Provider::create([
            'name' => 'Test Provider',
            'api_url' => 'https://provider.test/api',
            'api_key' => 'secret-key',
            'status' => 'active',
        ]);

        $service = Service::create([
            'name' => 'Instagram Followers',
            'platform' => 'instagram',
            'category' => 'Followers',
            'rate' => 100,
            'min_quantity' => 10,
            'max_quantity' => 1000,
            'provider_id' => $provider->id,
            'provider_service_id' => 'insta_1',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['balance' => 100000, 'total_spent' => 0]);
        Sanctum::actingAs($user);

        $this->postJson('/api/orders', [
            'service_id' => $service->id,
            'link' => 'https://instagram.com/test',
            'quantity' => 100,
        ])->assertCreated()->assertJsonPath('status', 'failed');

        Http::assertSentCount(1);
    }
}
