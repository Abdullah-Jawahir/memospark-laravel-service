<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('goal_types', function (Blueprint $table) {
      $table->id();
      $table->string('name');
      $table->text('description')->nullable();
      $table->string('unit'); // e.g., 'cards', 'minutes', 'points'
      $table->string('category');
      $table->boolean('is_active')->default(true);
      $table->integer('default_value')->default(0);
      $table->integer('min_value')->default(0);
      $table->integer('max_value')->default(1000);
      $table->timestamps();

      $table->index(['category', 'is_active']);
      $table->index('is_active');
    });

    // Insert default goal types
    DB::table('goal_types')->insert([
      [
        'name' => 'Daily Flashcards',
        'description' => 'Number of flashcards to review daily',
        'unit' => 'cards',
        'category' => 'study',
        'is_active' => true,
        'default_value' => 50,
        'min_value' => 1,
        'max_value' => 500,
        'created_at' => now(),
        'updated_at' => now()
      ],
      [
        'name' => 'Study Time',
        'description' => 'Daily study time goal in minutes',
        'unit' => 'minutes',
        'category' => 'time',
        'is_active' => true,
        'default_value' => 30,
        'min_value' => 5,
        'max_value' => 480,
        'created_at' => now(),
        'updated_at' => now()
      ],
      [
        'name' => 'Weekly Achievements',
        'description' => 'Number of achievements to unlock per week',
        'unit' => 'achievements',
        'category' => 'achievement',
        'is_active' => true,
        'default_value' => 2,
        'min_value' => 1,
        'max_value' => 10,
        'created_at' => now(),
        'updated_at' => now()
      ],
      [
        'name' => 'Engagement Score',
        'description' => 'Daily platform engagement score',
        'unit' => 'points',
        'category' => 'engagement',
        'is_active' => true,
        'default_value' => 100,
        'min_value' => 10,
        'max_value' => 1000,
        'created_at' => now(),
        'updated_at' => now()
      ]
    ]);
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('goal_types');
  }
};
