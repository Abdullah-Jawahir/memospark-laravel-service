<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Universal migration — safe to run on both MySQL (local) and PostgreSQL (Supabase/Cloud Run).
 * Uses Schema::hasColumn() and Schema::hasTable() guards so it is fully idempotent:
 * running it twice on the same database will never fail or duplicate data.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. users — add supabase_user_id ───────────────────
        if (!Schema::hasColumn('users', 'supabase_user_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('supabase_user_id')->nullable()->after('id');
                $table->index('supabase_user_id', 'idx_users_supabase_user_id');
            });
        }

        // ── 2. goal_types ─────────────────────────────────────
        if (!Schema::hasTable('goal_types')) {
            Schema::create('goal_types', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('unit');
                $table->string('category'); // study | engagement | achievement | time
                $table->boolean('is_active')->default(true);
                $table->integer('default_value')->default(0);
                $table->integer('min_value')->default(0);
                $table->integer('max_value')->default(1000);
                $table->timestamps();

                $table->index(['category', 'is_active']);
                $table->index('is_active');
            });

            // Seed default goal types
            DB::table('goal_types')->insert([
                [
                    'name'          => 'Daily Flashcards',
                    'description'   => 'Number of flashcards to review daily',
                    'unit'          => 'cards',
                    'category'      => 'study',
                    'is_active'     => true,
                    'default_value' => 50,
                    'min_value'     => 1,
                    'max_value'     => 500,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ],
                [
                    'name'          => 'Study Time',
                    'description'   => 'Daily study time goal in minutes',
                    'unit'          => 'minutes',
                    'category'      => 'time',
                    'is_active'     => true,
                    'default_value' => 30,
                    'min_value'     => 5,
                    'max_value'     => 480,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ],
                [
                    'name'          => 'Weekly Achievements',
                    'description'   => 'Number of achievements to unlock per week',
                    'unit'          => 'achievements',
                    'category'      => 'achievement',
                    'is_active'     => true,
                    'default_value' => 2,
                    'min_value'     => 1,
                    'max_value'     => 10,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ],
                [
                    'name'          => 'Engagement Score',
                    'description'   => 'Daily platform engagement score',
                    'unit'          => 'points',
                    'category'      => 'engagement',
                    'is_active'     => true,
                    'default_value' => 100,
                    'min_value'     => 10,
                    'max_value'     => 1000,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ],
            ]);
        }

        // ── 3. user_goals — add missing columns ───────────────
        Schema::table('user_goals', function (Blueprint $table) {
            if (!Schema::hasColumn('user_goals', 'goal_type_id')) {
                $table->foreignId('goal_type_id')->nullable()->after('user_id')->constrained('goal_types')->nullOnDelete();
                $table->index(['user_id', 'goal_type_id'], 'idx_ug_user_goal_type');
            }
            if (!Schema::hasColumn('user_goals', 'target_value')) {
                $table->integer('target_value')->default(0)->after('goal_type_id');
            }
            if (!Schema::hasColumn('user_goals', 'current_value')) {
                $table->integer('current_value')->default(0)->after('target_value');
            }
            if (!Schema::hasColumn('user_goals', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('current_value');
                $table->index(['user_id', 'is_active'], 'idx_ug_user_active');
            }
        });

        // ── 4. search_flashcard_searches ──────────────────────
        if (!Schema::hasTable('search_flashcard_searches')) {
            Schema::create('search_flashcard_searches', function (Blueprint $table) {
                $table->id();
                $table->string('user_id');
                $table->string('topic');
                $table->text('description')->nullable();
                $table->string('difficulty')->default('beginner');
                $table->integer('requested_count');
                $table->string('job_id')->unique();
                $table->string('status')->default('queued');
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'created_at'], 'sf_searches_user_created_idx');
                $table->index(['status', 'created_at'],  'sf_searches_status_created_idx');
                $table->index('job_id',                   'sf_searches_job_id_idx');
            });
        }

        // ── 5. search_flashcard_results ───────────────────────
        if (!Schema::hasTable('search_flashcard_results')) {
            Schema::create('search_flashcard_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('search_id')->constrained('search_flashcard_searches')->cascadeOnDelete();
                $table->text('question');
                $table->text('answer');
                $table->string('type')->default('Q&A');
                $table->string('difficulty');
                $table->integer('order_index');
                $table->timestamps();
                $table->index(['search_id', 'order_index'], 'sf_results_search_order_idx');
            });
        }

        // ── 6. search_flashcard_study_sessions ────────────────
        if (!Schema::hasTable('search_flashcard_study_sessions')) {
            Schema::create('search_flashcard_study_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('search_id')->constrained('search_flashcard_searches')->cascadeOnDelete();
                $table->string('user_id');
                $table->timestamp('started_at');
                $table->timestamp('completed_at')->nullable();
                $table->integer('total_flashcards');
                $table->integer('studied_flashcards')->default(0);
                $table->integer('correct_answers')->default(0);
                $table->integer('incorrect_answers')->default(0);
                $table->json('study_data')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'created_at'], 'sf_sessions_user_created_idx');
                $table->index('search_id',               'sf_sessions_search_id_idx');
            });
        }

        // ── 7. search_flashcard_study_records ─────────────────
        if (!Schema::hasTable('search_flashcard_study_records')) {
            Schema::create('search_flashcard_study_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('study_session_id')->constrained('search_flashcard_study_sessions')->cascadeOnDelete();
                $table->foreignId('flashcard_id')->constrained('search_flashcard_results')->cascadeOnDelete();
                $table->string('result')->nullable();
                $table->integer('time_spent')->nullable();
                $table->integer('attempts')->default(1);
                $table->timestamp('answered_at')->nullable();
                $table->timestamps();
                $table->index(['study_session_id', 'flashcard_id'], 'sf_records_session_flashcard_idx');
                $table->index('flashcard_id',                        'sf_records_flashcard_idx');
            });
        }

        // ── 8. search_flashcard_reviews ───────────────────────
        if (!Schema::hasTable('search_flashcard_reviews')) {
            Schema::create('search_flashcard_reviews', function (Blueprint $table) {
                $table->id();
                $table->string('user_id');
                $table->foreignId('search_id')->constrained('search_flashcard_searches')->cascadeOnDelete();
                $table->foreignId('flashcard_id')->constrained('search_flashcard_results')->cascadeOnDelete();
                $table->string('rating');
                $table->timestamp('reviewed_at');
                $table->integer('study_time')->default(0);
                $table->string('session_id')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'session_id'],  'idx_sfreviews_user_session');
                $table->index(['user_id', 'search_id'],   'idx_sfreviews_user_search');
                $table->index(['flashcard_id', 'user_id'], 'idx_sfreviews_flashcard_user');
                $table->index('reviewed_at',              'idx_sfreviews_reviewed_at');
            });
        }

        // ── 9. study_activity_timings ─────────────────────────
        if (!Schema::hasTable('study_activity_timings')) {
            Schema::create('study_activity_timings', function (Blueprint $table) {
                $table->id();
                $table->string('session_id');
                $table->string('user_id')->nullable();
                $table->string('activity_type'); // flashcard | quiz | exercise
                $table->timestamp('start_time');
                $table->timestamp('end_time')->nullable();
                $table->integer('duration_seconds');
                $table->json('activity_details')->nullable();
                $table->timestamps();

                $table->index('session_id',                        'idx_sat_session_id');
                $table->index(['session_id', 'activity_type'],     'idx_sat_session_type');
                $table->index(['session_id', 'created_at'],        'idx_sat_session_created');
                $table->index('user_id',                           'idx_sat_user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('search_flashcard_study_records');
        Schema::dropIfExists('search_flashcard_study_sessions');
        Schema::dropIfExists('search_flashcard_reviews');
        Schema::dropIfExists('search_flashcard_results');
        Schema::dropIfExists('search_flashcard_searches');
        Schema::dropIfExists('study_activity_timings');

        if (Schema::hasTable('user_goals')) {
            Schema::table('user_goals', function (Blueprint $table) {
                if (Schema::hasColumn('user_goals', 'goal_type_id')) {
                    $foreignKeyName = 'user_goals_goal_type_id_foreign';

                    if (Schema::hasIndex('user_goals', $foreignKeyName)) {
                        $table->dropForeign($foreignKeyName);
                    }

                    if (Schema::hasIndex('user_goals', 'idx_ug_user_goal_type')) {
                        $table->dropIndex('idx_ug_user_goal_type');
                    }
                }

                if (Schema::hasColumn('user_goals', 'is_active') && Schema::hasIndex('user_goals', 'idx_ug_user_active')) {
                    $table->dropIndex('idx_ug_user_active');
                }

                $columnsToDrop = [];

                foreach (['goal_type_id', 'target_value', 'current_value', 'is_active'] as $column) {
                    if (Schema::hasColumn('user_goals', $column)) {
                        $columnsToDrop[] = $column;
                    }
                }

                if ($columnsToDrop !== []) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }

        Schema::dropIfExists('goal_types');

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasIndex('users', 'idx_users_supabase_user_id')) {
                    $table->dropIndex('idx_users_supabase_user_id');
                }

                if (Schema::hasColumn('users', 'supabase_user_id')) {
                    $table->dropColumn('supabase_user_id');
                }
            });
        }
    }
};
