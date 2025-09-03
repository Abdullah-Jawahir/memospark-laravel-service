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
    Schema::table('flashcard_reviews', function (Blueprint $table) {
      if (!Schema::hasColumn('flashcard_reviews', 'study_time')) {
        $table->integer('study_time')->default(0)->after('session_id');
      }

      // Useful indexes for performance
      $table->index(['user_id', 'reviewed_at'], 'idx_user_reviewed_at');
      $table->index(['user_id', 'study_material_id'], 'idx_user_study_material');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('flashcard_reviews', function (Blueprint $table) {
      if (Schema::hasColumn('flashcard_reviews', 'study_time')) {
        $table->dropColumn('study_time');
      }
      $table->dropIndex('idx_user_reviewed_at');
      $table->dropIndex('idx_user_study_material');
    });
  }
};
