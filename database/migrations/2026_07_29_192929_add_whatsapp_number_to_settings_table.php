<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->insert([
            'key' => 'whatsapp_number',
            'value' => '',
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'whatsapp_number')->delete();
    }
};
