<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('simulators', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->text('description')->nullable();

            // disponibilidad
            $table->dateTime('available_from')->nullable();
            $table->dateTime('available_until')->nullable();

            // intentos: null = ilimitados, si no es null = max attempts
            $table->unsignedSmallInteger('max_attempts')->nullable();

            // tiempo limite en segundos (null = sin limite)
            $table->unsignedInteger('time_limit_seconds')->nullable();

            // minima aprobatoria 0..100 (null = sin minima)
            $table->unsignedTinyInteger('min_passing_score')->nullable();

            $table->boolean('shuffle_questions')->default(true);
            $table->boolean('shuffle_options')->default(true);

            $table->boolean('is_published')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulators');
    }
};
