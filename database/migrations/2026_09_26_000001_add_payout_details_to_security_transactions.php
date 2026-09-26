<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_transactions', function (Blueprint $table) {
            $table->string('payout_method')->nullable();
            $table->string('payout_reference')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('security_transactions', function (Blueprint $table) {
            $table->dropColumn(['payout_method', 'payout_reference']);
        });
    }
};
