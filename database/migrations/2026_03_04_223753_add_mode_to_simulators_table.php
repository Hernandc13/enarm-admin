<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('simulators', function (Blueprint $table) {
            // study = modo estudio, exam = modo examen
            $table->string('mode', 20)->default('exam')->after('description')->index();
        });
    }

    public function down(): void
    {
        Schema::table('simulators', function (Blueprint $table) {
            $table->dropIndex(['mode']);
            $table->dropColumn('mode');
        });
    }
};
