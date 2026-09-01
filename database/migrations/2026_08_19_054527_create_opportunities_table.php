<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('lead_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('client_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('assigned_to_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('stage')
                ->default('initial_contact');

            $table->unsignedInteger('manpower_requirement')
                ->nullable();

            $table->decimal('estimated_contract_value', 15, 2)
                ->nullable();

            $table->date('expected_close_date')
                ->nullable();

            $table->text('lost_reason')
                ->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
