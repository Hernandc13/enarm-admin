<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            if (Schema::hasColumn('questions', 'reference') && !Schema::hasColumn('questions', 'general_feedback')) {
                $table->renameColumn('reference', 'general_feedback');
            }
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            if (Schema::hasColumn('questions', 'general_feedback') && !Schema::hasColumn('questions', 'reference')) {
                $table->renameColumn('general_feedback', 'reference');
            }
        });
    }
};