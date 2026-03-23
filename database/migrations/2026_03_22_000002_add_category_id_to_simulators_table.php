<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('simulators', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('id')
                ->constrained('simulator_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('simulators', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};