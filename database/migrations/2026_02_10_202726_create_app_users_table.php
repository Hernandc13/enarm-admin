<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_users', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('moodle_user_id')->unique();
            $table->string('email')->index();
            $table->string('name');
            $table->string('last_name')->nullable();

            $table->boolean('has_app_access')->default(true)->index(); // acceso activo
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_users');
    }
};
