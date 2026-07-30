<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('gateway')->nullable()->after('method');
            $table->string('gateway_status')->nullable()->after('status');
            $table->string('gateway_transaction_id')->nullable()->after('gateway_status');
            $table->timestamp('credited_at')->nullable()->after('gateway_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['gateway', 'gateway_status', 'gateway_transaction_id', 'credited_at']);
        });
    }
};
