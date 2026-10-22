<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('members', 'members_phone_unique')) {
            Schema::table('members', function ($table) {
                $table->dropUnique('members_phone_unique');
            });
        }

        if (! Schema::hasIndex('members', 'members_phone_index')) {
            Schema::table('members', function ($table) {
                $table->index('phone');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('members', 'members_phone_index')) {
            Schema::table('members', function ($table) {
                $table->dropIndex(['phone']);
            });
        }

        if (! Schema::hasIndex('members', 'members_phone_unique')) {
            Schema::table('members', function ($table) {
                $table->unique('phone');
            });
        }
    }
};
