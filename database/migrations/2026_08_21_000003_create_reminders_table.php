<?php

use App\Enums\ReminderPriority;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('related_to_type')->default('lead');
            $table->unsignedBigInteger('related_to_id');

            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date');
            $table->string('priority')->default(ReminderPriority::MEDIUM->value);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->string('assigned_to_name')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
