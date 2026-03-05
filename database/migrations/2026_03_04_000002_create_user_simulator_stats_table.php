<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_simulator_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('simulator_id')->constrained('simulators')->cascadeOnDelete();

            $table->unsignedInteger('attempts_count')->default(0);
            $table->unsignedInteger('sum_scores')->default(0); // suma de scores (0-100)
            $table->decimal('avg_score', 5, 2)->default(0);
            $table->unsignedInteger('best_score')->default(0);
            $table->unsignedInteger('last_score')->default(0);
            $table->timestamp('last_attempt_at')->nullable();

            $table->timestamps();
            $table->unique(['user_id', 'simulator_id']);
            $table->index(['simulator_id', 'best_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_simulator_stats');
    }
};