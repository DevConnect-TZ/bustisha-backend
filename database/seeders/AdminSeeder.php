<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'username' => 'admin',
            'email' => 'admin@smm.com',
            'password' => Hash::make('password'),
            'balance' => 0,
            'total_spent' => 0,
            'role' => 'admin',
            'status' => 'active',
        ]);
    }
}
