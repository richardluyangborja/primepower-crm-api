<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_template_versions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('survey_template_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedInteger('version');
            $table->json('questions');
            $table->boolean('is_current')->default(false);

            $table->timestamps();

            $table->unique(['survey_template_id', 'version']);
            $table->index('is_current');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_template_versions');
    }
};
