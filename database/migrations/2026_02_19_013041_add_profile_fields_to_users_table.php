<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ✅ Solo aplica a usuarios manual/excel (no moodle / no admin), pero en DB quedan nullable.
            $table->string('origin_university')->nullable()->after('last_name');
            $table->string('origin_municipality')->nullable()->after('origin_university');
            $table->string('desired_specialty')->nullable()->after('origin_municipality');
            $table->string('whatsapp_number', 30)->nullable()->after('desired_specialty');

            $table->index('origin_university');
            $table->index('origin_municipality');
            $table->index('desired_specialty');
            $table->index('whatsapp_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['origin_university']);
            $table->dropIndex(['origin_municipality']);
            $table->dropIndex(['desired_specialty']);
            $table->dropIndex(['whatsapp_number']);

            $table->dropColumn([
                'origin_university',
                'origin_municipality',
                'desired_specialty',
                'whatsapp_number',
            ]);
        });
    }
};
