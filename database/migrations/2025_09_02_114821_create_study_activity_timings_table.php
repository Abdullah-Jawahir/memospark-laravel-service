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
        Schema::create('study_activity_timings', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->index();
            $table->enum('activity_type', ['flashcard', 'quiz', 'exercise']);
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->integer('duration_seconds');
            $table->json('activity_details')->nullable(); // Store card_id, quiz_step, etc.
            $table->timestamps();

            $table->index(['session_id', 'activity_type']);
            $table->index(['session_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_activity_timings');
    }
};
