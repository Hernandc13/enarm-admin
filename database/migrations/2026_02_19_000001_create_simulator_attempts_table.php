<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('simulator_attempts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('simulator_id')->constrained('simulators')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->unsignedInteger('total_questions')->default(0);
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedSmallInteger('score')->default(0);

            $table->string('status', 20)->default('in_progress'); // in_progress | finished | expired

            $table->timestamps();

            $table->index(['user_id', 'simulator_id']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulator_attempts');
    }
};
