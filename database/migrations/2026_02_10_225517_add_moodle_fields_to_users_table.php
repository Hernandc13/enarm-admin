<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('moodle_user_id')->nullable()->unique()->after('id');
            $table->boolean('is_from_moodle')->default(false)->after('moodle_user_id');

            $table->boolean('has_app_access')->default(false)->after('is_from_moodle');
            $table->timestamp('granted_at')->nullable()->after('has_app_access');
            $table->timestamp('revoked_at')->nullable()->after('granted_at');
            $table->timestamp('synced_at')->nullable()->after('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['moodle_user_id']);
            $table->dropColumn([
                'moodle_user_id',
                'is_from_moodle',
                'has_app_access',
                'granted_at',
                'revoked_at',
                'synced_at',
            ]);
        });
    }
};
