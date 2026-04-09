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
        // Table for storing search history
        Schema::create('search_flashcard_searches', function (Blueprint $table) {
            $table->id();
            $table->string('user_id'); // Supabase user ID
            $table->string('topic');
            $table->text('description')->nullable();
            $table->string('difficulty')->default('beginner');
            $table->integer('requested_count');
            $table->string('job_id')->unique(); // UUID from the job
            $table->string('status')->default('queued');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes for better performance
            $table->index(['user_id', 'created_at'], 'sf_searches_user_created_idx');
            $table->index(['status', 'created_at'], 'sf_searches_status_created_idx');
            $table->index('job_id', 'sf_searches_job_id_idx');
        });

        // Table for storing generated flashcards
        Schema::create('search_flashcard_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('search_id')->constrained('search_flashcard_searches')->cascadeOnDelete();
            $table->text('question');
            $table->text('answer');
            $table->string('type')->default('Q&A');
            $table->string('difficulty');
            $table->integer('order_index'); // To maintain the order of flashcards
            $table->timestamps();

            // Indexes
            $table->index(['search_id', 'order_index'], 'sf_results_search_order_idx');
        });

        // Table for storing study sessions with these flashcards
        Schema::create('search_flashcard_study_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('search_id')->constrained('search_flashcard_searches')->cascadeOnDelete();
            $table->string('user_id'); // Supabase user ID
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('total_flashcards');
            $table->integer('studied_flashcards')->default(0);
            $table->integer('correct_answers')->default(0);
            $table->integer('incorrect_answers')->default(0);
            $table->json('study_data')->nullable(); // Store additional study metrics
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'created_at'], 'sf_sessions_user_created_idx');
            $table->index('search_id', 'sf_sessions_search_id_idx');
        });

        // Table for storing individual flashcard study records
        Schema::create('search_flashcard_study_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_session_id')->constrained('search_flashcard_study_sessions')->cascadeOnDelete();
            $table->foreignId('flashcard_id')->constrained('search_flashcard_results')->cascadeOnDelete();
            $table->string('result')->nullable();
            $table->integer('time_spent')->nullable(); // Time in seconds
            $table->integer('attempts')->default(1);
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['study_session_id', 'flashcard_id'], 'sf_records_session_flashcard_idx');
            $table->index('flashcard_id', 'sf_records_flashcard_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_flashcard_study_records');
        Schema::dropIfExists('search_flashcard_study_sessions');
        Schema::dropIfExists('search_flashcard_results');
        Schema::dropIfExists('search_flashcard_searches');
    }
};
