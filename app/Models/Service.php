<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'platform', 'category', 'rate', 'min_quantity', 'max_quantity', 'description', 'is_active',
        'provider_id', 'provider_service_id',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function orders() { return $this->hasMany(Order::class); }
    public function provider() { return $this->belongsTo(\App\Models\Provider::class); }
}
