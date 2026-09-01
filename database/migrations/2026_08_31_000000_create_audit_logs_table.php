<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Actor (the user who performed the action) - stored as strings only
            // so the history is preserved even if the user record is changed/deleted.
            $table->string('actor_name')->nullable();
            $table->string('actor_email')->nullable();
            $table->string('actor_role')->nullable();

            // What happened.
            $table->string('module');
            $table->string('action');

            // Subject of the action - stored as strings only (no foreign keys)
            // so the history survives even if the related record is updated/deleted.
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->string('subject_name')->nullable();

            $table->text('description')->nullable();

            // Detailed, structured context (old/new values, ids, etc.) stored as JSON text.
            $table->text('metadata')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
