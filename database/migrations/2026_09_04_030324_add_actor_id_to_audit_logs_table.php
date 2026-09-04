<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('actor_id')->nullable()->after('actor_role')->constrained('users')->nullOnDelete();
            $table->index('actor_id');
            $table->index(['module', 'action']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['actor_id']);
            $table->dropIndex(['actor_id']);
            $table->dropIndex(['module', 'action']);
            $table->dropIndex(['created_at']);
            $table->dropColumn('actor_id');
        });
    }
};
