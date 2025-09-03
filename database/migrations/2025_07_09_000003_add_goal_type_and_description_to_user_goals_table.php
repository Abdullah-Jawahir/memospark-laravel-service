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
    Schema::table('user_goals', function (Blueprint $table) {
      $table->enum('goal_type', ['cards_studied', 'study_time', 'decks_completed'])->default('cards_studied')->after('daily_goal');
      $table->string('description')->nullable()->after('goal_type');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('user_goals', function (Blueprint $table) {
      $table->dropColumn(['goal_type', 'description']);
    });
  }
};
