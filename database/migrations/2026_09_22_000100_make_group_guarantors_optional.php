<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('loan_products')->update(['required_group_witnesses' => 0]);

        Schema::table('loan_products', function ($table) {
            $table->unsignedInteger('required_group_witnesses')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function ($table) {
            $table->unsignedInteger('required_group_witnesses')->default(2)->change();
        });
    }
};