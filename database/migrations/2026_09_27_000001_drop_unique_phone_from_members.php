<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function ($table) {
            $table->dropUnique('members_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::table('members', function ($table) {
            $table->string('phone')->nullable(false)->unique()->change();
        });
    }
};
