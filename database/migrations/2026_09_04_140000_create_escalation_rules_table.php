<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalation_rules', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            $table->string('entity_type');
            $table->string('condition');
            $table->unsignedInteger('threshold_days')->nullable();

            $table->string('action_type');

            $table->string('reminder_title')->nullable();
            $table->string('reminder_priority')->nullable();
            $table->unsignedInteger('reminder_due_in_days')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalation_rules');
    }
};
