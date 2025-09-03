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
      $table->integer('study_time')->default(0)->comment('Study time in seconds');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('flashcard_reviews', function (Blueprint $table) {
      $table->dropColumn('study_time');
    });
  }
};
