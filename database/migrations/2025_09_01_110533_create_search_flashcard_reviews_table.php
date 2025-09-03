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
        Schema::create('search_flashcard_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('user_id'); // Supabase UUID
            $table->unsignedBigInteger('search_id');
            $table->unsignedBigInteger('flashcard_id');
            $table->enum('rating', ['again', 'hard', 'good', 'easy']);
            $table->timestamp('reviewed_at');
            $table->integer('study_time')->default(0); // in seconds
            $table->string('session_id')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('search_id')->references('id')->on('search_flashcard_searches')->onDelete('cascade');
            $table->foreign('flashcard_id')->references('id')->on('search_flashcard_results')->onDelete('cascade');

            // Indexes for performance
            $table->index(['user_id', 'session_id']);
            $table->index(['user_id', 'search_id']);
            $table->index(['flashcard_id', 'user_id']);
            $table->index('reviewed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_flashcard_reviews');
    }
};
