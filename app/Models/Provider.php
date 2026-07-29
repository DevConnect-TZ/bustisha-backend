<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'api_url', 'api_key', 'balance', 'status'];

    protected function casts(): array
    {
        return ['balance' => 'decimal:2'];
    }
}
