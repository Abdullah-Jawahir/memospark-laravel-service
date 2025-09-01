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
      // Add new columns for enhanced goal system
      $table->uuid('goal_type_id')->nullable()->after('user_id');
      $table->integer('target_value')->default(0)->after('goal_type_id');
      $table->integer('current_value')->default(0)->after('target_value');
      $table->boolean('is_active')->default(true)->after('current_value');

      // Add foreign key constraint
      $table->foreign('goal_type_id')->references('id')->on('goal_types')->onDelete('set null');

      // Add indexes
      $table->index(['user_id', 'goal_type_id']);
      $table->index(['user_id', 'is_active']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('user_goals', function (Blueprint $table) {
      $table->dropForeign(['goal_type_id']);
      $table->dropIndex(['user_id', 'goal_type_id']);
      $table->dropIndex(['user_id', 'is_active']);
      $table->dropColumn(['goal_type_id', 'target_value', 'current_value', 'is_active']);
    });
  }
};
