<?php

use App\Enums\ClientSurveyStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_surveys', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('token')->unique();
            $table->string('status')->default(ClientSurveyStatus::PENDING->value);
            $table->json('responses')->nullable();
            $table->decimal('average_score', 3, 2)->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_surveys');
    }
};
