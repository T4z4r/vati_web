<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_groups', function (Blueprint $table) {
            $table->string('meeting_location')->nullable()->after('meeting_time');
        });
    }

    public function down(): void
    {
        Schema::table('member_groups', function (Blueprint $table) {
            $table->dropColumn('meeting_location');
        });
    }
};