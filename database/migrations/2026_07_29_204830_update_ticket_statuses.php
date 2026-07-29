<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tickets')->where('status', 'open')->update(['status' => 'pending']);

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });
    }

    public function down(): void
    {
        DB::table('tickets')->where('status', 'pending')->update(['status' => 'open']);

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('status')->default('open')->change();
        });
    }
};
