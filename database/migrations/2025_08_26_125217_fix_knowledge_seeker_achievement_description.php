<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Achievement;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fix all achievement data based on the correct information from DashboardController
        $achievementsData = [
            'Study Streak' => [
                'description' => '10 days in a row',
                'icon' => 'trophy',
                'criteria' => 'study_streak_10',
                'points' => 100
            ],
            'Speed Learner' => [
                'description' => 'Earned 50 points in the system',
                'icon' => 'lightning-bolt',
                'criteria' => null,
                'points' => 50
            ],
            'Deck Master' => [
                'description' => 'Created 5 decks',
                'icon' => 'stack-books',
                'criteria' => 'decks_created_5',
                'points' => 150
            ],
            'Consistent Learner' => [
                'description' => '30 days in a row',
                'icon' => 'calendar-check',
                'criteria' => 'study_streak_30',
                'points' => 500
            ],
            'Knowledge Seeker' => [
                'description' => 'Earned 100 points in the system',
                'icon' => '🧠',
                'criteria' => null,
                'points' => 100
            ],
            'Getting Started' => [
                'description' => 'Earned 10 points in the system',
                'icon' => '🎯',
                'criteria' => null,
                'points' => 10
            ],
            'Memory Master' => [
                'description' => 'Earned 250 points in the system',
                'icon' => '🏆',
                'criteria' => null,
                'points' => 250
            ],
            'Study Champion' => [
                'description' => 'Earned 500 points in the system',
                'icon' => '🥇',
                'criteria' => null,
                'points' => 500
            ]
        ];

        foreach ($achievementsData as $name => $data) {
            $achievement = Achievement::where('name', $name)->first();

            if ($achievement) {
                $achievement->description = $data['description'];
                $achievement->icon = $data['icon'];
                $achievement->criteria = $data['criteria'];
                $achievement->points = $data['points'];
                $achievement->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore original values - using data from your screenshot
        $originalData = [
            'Study Streak' => [
                'description' => '10 days in a row',
                'icon' => 'trophy',
                'criteria' => 'study_streak_10',
                'points' => 100
            ],
            'Speed Learner' => [
                'description' => 'Completed 10 cards',
                'icon' => 'lightning-bolt',
                'criteria' => 'cards_completed_100',
                'points' => 200
            ],
            'Deck Master' => [
                'description' => 'Created 5 decks',
                'icon' => 'stack-books',
                'criteria' => 'decks_created_5',
                'points' => 150
            ],
            'Consistent Learner' => [
                'description' => '30 days in a row',
                'icon' => 'calendar-check',
                'criteria' => 'study_streak_30',
                'points' => 500
            ],
            'Knowledge Seeker' => [
                'description' => 'Completed 500 cards',
                'icon' => 'graduation-cap',
                'criteria' => 'cards_completed_500',
                'points' => 1000
            ]
        ];

        foreach ($originalData as $name => $data) {
            $achievement = Achievement::where('name', $name)->first();

            if ($achievement) {
                $achievement->description = $data['description'];
                $achievement->icon = $data['icon'];
                $achievement->criteria = $data['criteria'];
                $achievement->points = $data['points'];
                $achievement->save();
            }
        }

        // Remove any achievements we might have created in the up() method
        Achievement::whereIn('name', ['Getting Started', 'Memory Master', 'Study Champion'])
            ->whereNotIn('name', array_keys($originalData))
            ->delete();
    }
};
