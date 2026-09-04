<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalation_triggers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('escalation_rule_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');

            $table->timestamp('triggered_at');

            $table->unique(
                ['escalation_rule_id', 'entity_type', 'entity_id'],
                'escalation_triggers_rule_entity_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalation_triggers');
    }
};
