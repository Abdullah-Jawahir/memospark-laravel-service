<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Achievement;

class AchievementSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    $achievements = [
      [
        'name' => 'Study Streak',
        'description' => '10 days in a row',
        'icon' => 'trophy',
        'criteria' => 'study_streak_10',
        'points' => 100
      ],
      [
        'name' => 'Speed Learner',
        'description' => 'Completed 100 cards',
        'icon' => 'lightning-bolt',
        'criteria' => 'cards_completed_100',
        'points' => 200
      ],
      [
        'name' => 'Deck Master',
        'description' => 'Created 5 decks',
        'icon' => 'stack-books',
        'criteria' => 'decks_created_5',
        'points' => 150
      ],
      [
        'name' => 'Consistent Learner',
        'description' => '30 days in a row',
        'icon' => 'calendar-check',
        'criteria' => 'study_streak_30',
        'points' => 500
      ],
      [
        'name' => 'Knowledge Seeker',
        'description' => 'Completed 500 cards',
        'icon' => 'graduation-cap',
        'criteria' => 'cards_completed_500',
        'points' => 1000
      ]
    ];

    foreach ($achievements as $achievement) {
      Achievement::firstOrCreate(
        ['name' => $achievement['name']],
        $achievement
      );
    }
  }
}
