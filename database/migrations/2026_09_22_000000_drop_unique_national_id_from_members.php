<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function ($table) {
            $table->dropUnique('members_national_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('members', function ($table) {
            $table->string('national_id')->nullable()->unique()->change();
        });
    }
};