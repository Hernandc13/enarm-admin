<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('simulator_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('simulator_id')->constrained('simulators')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->unsignedSmallInteger('order')->nullable();
            $table->timestamps();

            $table->unique(['simulator_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulator_questions');
    }
};
