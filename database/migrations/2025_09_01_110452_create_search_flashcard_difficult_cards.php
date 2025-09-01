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
        Schema::create('search_flashcard_difficult_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('search_id');
            $table->unsignedBigInteger('flashcard_id');
            $table->enum('status', ['marked_difficult', 'reviewed', 're_rated'])->default('marked_difficult');
            $table->timestamp('marked_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('re_rated_at')->nullable();
            $table->enum('final_rating', ['again', 'hard', 'good', 'easy'])->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('search_id')->references('id')->on('search_flashcard_searches')->onDelete('cascade');
            $table->foreign('flashcard_id')->references('id')->on('search_flashcard_results')->onDelete('cascade');

            // Unique constraint to prevent duplicate difficult card entries
            $table->unique(['user_id', 'search_id', 'flashcard_id'], 'unique_user_search_flashcard');

            // Indexes for better performance
            $table->index(['user_id', 'status']);
            $table->index(['search_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_flashcard_difficult_cards');
    }
};
