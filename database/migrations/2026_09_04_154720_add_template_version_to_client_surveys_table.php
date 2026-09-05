<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_surveys', function (Blueprint $table) {
            $table->foreignId('template_version_id')
                ->nullable()
                ->after('client_id')
                ->constrained('survey_template_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('client_surveys', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_version_id');
        });
    }
};
