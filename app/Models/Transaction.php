<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'method', 'gateway', 'amount', 'status', 'phone', 'reference', 'gateway_status', 'gateway_transaction_id', 'credited_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'credited_at' => 'datetime'];
    }

    public function user() { return $this->belongsTo(User::class); }
}
