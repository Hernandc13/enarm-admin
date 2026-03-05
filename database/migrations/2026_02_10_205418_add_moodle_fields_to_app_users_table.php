<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('app_users', function (Blueprint $table) {
            if (!Schema::hasColumn('app_users', 'is_from_moodle')) {
                $table->boolean('is_from_moodle')->default(true)->after('last_name');
            }
            if (!Schema::hasColumn('app_users', 'synced_at')) {
                $table->timestamp('synced_at')->nullable()->after('revoked_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('app_users', function (Blueprint $table) {
            if (Schema::hasColumn('app_users', 'is_from_moodle')) {
                $table->dropColumn('is_from_moodle');
            }
            if (Schema::hasColumn('app_users', 'synced_at')) {
                $table->dropColumn('synced_at');
            }
        });
    }
};
