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
        // Fix existing achievements with incorrect data
        $achievementUpdates = [
            'Getting Started' => [
                'icon' => '🎯',
                'points' => 10
            ],
            'Fast Learner' => [
                'icon' => '🚀',
                'points' => 50
            ],
            'Speed Learner' => [
                'icon' => '🚀',
                'points' => 50
            ],
            'Knowledge Seeker' => [
                'icon' => '🧠',
                'points' => 100
            ],
            'Memory Master' => [
                'icon' => '🏆',
                'points' => 250
            ],
            'Study Champion' => [
                'icon' => '🥇',
                'points' => 500
            ],
        ];

        foreach ($achievementUpdates as $name => $data) {
            DB::table('achievements')
                ->where('name', $name)
                ->update([
                    'icon' => $data['icon'],
                    'points' => $data['points'],
                    'updated_at' => now()
                ]);
        }

        // Remove duplicate "Speed Learner" if it exists (we'll keep "Fast Learner")
        $speedLearner = DB::table('achievements')->where('name', 'Speed Learner')->first();
        $fastLearner = DB::table('achievements')->where('name', 'Fast Learner')->first();

        if ($speedLearner && $fastLearner) {
            // Transfer any user achievements from Speed Learner to Fast Learner
            DB::table('user_achievements')
                ->where('achievement_id', $speedLearner->id)
                ->update(['achievement_id' => $fastLearner->id]);

            // Delete the Speed Learner achievement
            DB::table('achievements')->where('id', $speedLearner->id)->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
