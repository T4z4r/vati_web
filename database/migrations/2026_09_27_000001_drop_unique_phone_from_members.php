<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function ($table) {
            $table->dropUnique('members_phone_unique');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::table('members', function ($table) {
            $table->dropIndex(['phone']);
            $table->unique('phone');
        });
    }
};
