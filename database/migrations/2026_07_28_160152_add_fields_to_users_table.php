<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('name');
            $table->string('phone')->nullable()->after('email');
            $table->decimal('balance', 15, 2)->default(0)->after('phone');
            $table->decimal('total_spent', 15, 2)->default(0)->after('balance');
            $table->string('role')->default('user')->after('total_spent');
            $table->string('status')->default('active')->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'phone', 'balance', 'total_spent', 'role', 'status']);
        });
    }
};
