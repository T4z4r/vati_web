<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name');
            $table->timestamps();
        });
        DB::table('system_settings')->insertOrIgnore([
            'key' => 'forward_holiday_repayments', 'value' => '0', 'type' => 'boolean',
            'group' => 'business_rules',
            'description' => 'Move repayments falling on holidays to the same weekday in the following week.',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
        DB::table('system_settings')->where('key', 'forward_holiday_repayments')->delete();
    }
};
