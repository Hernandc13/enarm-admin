<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('simulator_attempt_answers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('attempt_id')->constrained('simulator_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();

            // si tus opciones existen como IDs
            $table->unsignedBigInteger('selected_option_id')->nullable();

            // fallback si guardas texto
            $table->text('selected_text')->nullable();

            $table->boolean('is_correct')->default(false);

            $table->timestamps();

            $table->unique(['attempt_id', 'question_id']);
            $table->index(['attempt_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulator_attempt_answers');
    }
};
