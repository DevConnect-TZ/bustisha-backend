<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_select_gateway_and_api_key_is_never_returned(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/settings', [
            'active_payment_gateway' => 'sonicpesa',
            'sonicpesa_api_key' => 'secret-key',
        ])->assertOk();

        $this->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('active_payment_gateway', 'sonicpesa')
            ->assertJsonPath('sonicpesa_api_key_set', true)
            ->assertJsonMissing(['sonicpesa_api_key' => 'secret-key']);

        $this->assertSame('secret-key', Setting::getSecret('sonicpesa_api_key'));
        $this->assertNotSame('secret-key', Setting::getValue('sonicpesa_api_key'));
    }

    public function test_sonicpesa_payment_is_polled_and_credited_only_once(): void
    {
        Http::fake([
            '*api.sonicpesa.com/api/v1/payment/create_order' => Http::response([
                'status' => 'success', 'data' => ['order_id' => 'sp_123', 'payment_status' => 'PENDING'],
            ]),
            '*api.sonicpesa.com/api/v1/payment/order_status' => Http::response([
                'status' => 'success', 'data' => ['payment_status' => 'SUCCESS', 'transid' => 'tx_123'],
            ]),
        ]);

        Setting::updateOrCreate(['key' => 'active_payment_gateway'], ['value' => 'sonicpesa']);
        Setting::setSecret('sonicpesa_api_key', 'secret-key');
        $user = User::factory()->create(['balance' => 0]);
        Sanctum::actingAs($user);

        $transaction = $this->postJson('/api/transactions', [
            'method' => 'mpesa', 'phone' => '0712345678', 'amount' => 1000,
        ])->assertCreated()->assertJsonPath('gateway', 'sonicpesa')->json();

        $this->postJson("/api/transactions/{$transaction['id']}/status")->assertOk()->assertJsonPath('status', 'completed');
        $this->postJson("/api/transactions/{$transaction['id']}/status")->assertOk()->assertJsonPath('status', 'completed');

        $this->assertSame('1000.00', $user->fresh()->balance);
        $this->assertNotNull(Transaction::find($transaction['id'])->credited_at);
    }

    public function test_user_cannot_check_another_users_transaction(): void
    {
        $owner = User::factory()->create();
        $transaction = Transaction::create([
            'user_id' => $owner->id,
            'method' => 'mpesa',
            'gateway' => 'sonicpesa',
            'phone' => '255712345678',
            'amount' => 1000,
            'status' => 'pending',
            'reference' => 'sp_other',
        ]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/transactions/{$transaction->id}/status")->assertNotFound();
    }
}
