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
        Schema::create('study_session_timings', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->integer('total_study_time')->default(0);
            $table->integer('flashcard_time')->default(0);
            $table->integer('quiz_time')->default(0);
            $table->integer('exercise_time')->default(0);
            $table->timestamp('session_start')->nullable();
            $table->timestamp('session_end')->nullable();
            $table->timestamps();

            $table->index('session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_session_timings');
    }
};
