<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_surveys', function (Blueprint $table) {
            $table->string('respondent_name')->nullable()->after('average_score');
            $table->string('respondent_position')->nullable()->after('respondent_name');
            $table->text('feedback')->nullable()->after('respondent_position');
        });
    }

    public function down(): void
    {
        Schema::table('client_surveys', function (Blueprint $table) {
            $table->dropColumn(['respondent_name', 'respondent_position', 'feedback']);
        });
    }
};
