<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('member_groups')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('visit_date');
            $table->string('purpose')->nullable();
            $table->text('notes')->nullable();
            $table->string('location')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'visit_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_visits');
    }
};
