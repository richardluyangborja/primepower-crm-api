<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->foreignId('manager_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('team_id')
                ->nullable()
                ->after('role')
                ->constrained('teams')
                ->nullOnDelete();
            $table->foreignId('manager_id')
                ->nullable()
                ->after('team_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('manager_id');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropForeign(['manager_id']);
            $table->dropColumn(['team_id', 'manager_id', 'is_active']);
            $table->dropSoftDeletes();
        });

        Schema::dropIfExists('teams');
    }
};
