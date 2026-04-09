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
            $table->foreignId('search_id')->constrained('search_flashcard_searches')->cascadeOnDelete();
            $table->foreignId('flashcard_id')->constrained('search_flashcard_results')->cascadeOnDelete();
            $table->string('rating');
            $table->timestamp('reviewed_at');
            $table->integer('study_time')->default(0); // in seconds
            $table->string('session_id')->nullable();
            $table->timestamps();

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
