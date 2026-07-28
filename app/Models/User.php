<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name', 'email', 'password', 'username', 'phone', 'balance', 'total_spent', 'role', 'status',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'balance' => 'decimal:2',
            'total_spent' => 'decimal:2',
        ];
    }

    public function orders() { return $this->hasMany(Order::class); }
    public function tickets() { return $this->hasMany(Ticket::class); }
    public function transactions() { return $this->hasMany(Transaction::class); }
}
