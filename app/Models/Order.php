<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'service_id', 'link', 'quantity', 'charge', 'status', 'provider_order_id', 'provider_response'];

    protected $appends = ['provider_status'];

    protected function casts(): array
    {
        return [
            'charge' => 'decimal:2',
            'provider_response' => 'array',
        ];
    }

    protected function providerStatus(): Attribute
    {
        return Attribute::get(fn () => is_array($this->provider_response)
            ? ($this->provider_response['status'] ?? null)
            : null);
    }

    public function user() { return $this->belongsTo(User::class); }
    public function service() { return $this->belongsTo(Service::class); }
}
