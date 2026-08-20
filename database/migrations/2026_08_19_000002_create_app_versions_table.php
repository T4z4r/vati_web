<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version_code', 20);
            $table->string('version_name', 50);
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->text('release_notes')->nullable();
            $table->boolean('is_latest')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};
