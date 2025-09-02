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
        Schema::dropIfExists('study_session_timings');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('study_session_timings', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->string('user_id')->nullable()->index();
            $table->timestamp('session_start');
            $table->timestamp('session_end')->nullable();
            $table->integer('total_study_time')->default(0); // in seconds
            $table->integer('flashcard_time')->default(0); // in seconds
            $table->integer('quiz_time')->default(0); // in seconds
            $table->integer('exercise_time')->default(0); // in seconds
            $table->json('session_data')->nullable(); // Store any additional session data
            $table->timestamps();
        });
    }
};
