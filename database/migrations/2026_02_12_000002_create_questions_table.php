<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('specialty_id')
                ->constrained('specialties')
                ->cascadeOnDelete();

            // ✅ Ya NO es opcional: siempre se genera (manual/import)
            // 50-80 suele bastar, pero dejamos 255 por seguridad
            $table->string('gift_id', 255)->nullable(false);

            $table->text('stem');       // enunciado
            $table->text('reference');  // [REF] obligatorio

            // hash para deduplicar si no hay ::ID:: (o para detectar idénticas)
            $table->string('content_hash', 64)->index();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // ✅ Único compuesto por especialidad
            $table->unique(['specialty_id', 'gift_id']);

            // ✅ Índice adicional solo si vas a buscar por gift_id sin specialty_id
            // (opcional; si no lo ocupas, lo puedes quitar)
            $table->index('gift_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
