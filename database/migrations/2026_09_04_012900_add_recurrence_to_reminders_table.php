<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->string('recurrence_rule')->nullable()->after('notes');
            $table->foreignId('recurrence_parent_id')->nullable()->after('recurrence_rule')->constrained('reminders')->nullOnDelete();
            $table->index(['recurrence_rule', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropForeign(['recurrence_parent_id']);
            $table->dropIndex(['recurrence_rule', 'completed_at']);
            $table->dropColumn(['recurrence_rule', 'recurrence_parent_id']);
        });
    }
};
