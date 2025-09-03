<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Deck;
use App\Models\Document;
use App\Models\StudyMaterial;
use App\Models\FlashcardReview;
use App\Models\UserGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DashboardTest extends TestCase
{
  use RefreshDatabase;

  public function test_dashboard_returns_user_data()
  {
    // Create a test user
    $user = User::factory()->create([
      'user_type' => 'student'
    ]);

    // Create a deck for the user
    $deck = Deck::create([
      'user_id' => $user->id,
      'name' => 'Test Deck'
    ]);

    // Create a document
    $document = Document::create([
      'user_id' => $user->id,
      'deck_id' => $deck->id,
      'original_filename' => 'test.pdf',
      'storage_path' => 'test/path',
      'file_type' => 'pdf',
      'language' => 'en',
      'status' => 'completed'
    ]);

    // Create study materials
    $studyMaterial = StudyMaterial::create([
      'document_id' => $document->id,
      'type' => 'flashcard',
      'content' => 'Test content',
      'metadata' => ['question' => 'Test question', 'answer' => 'Test answer']
    ]);

    // Create a flashcard review
    $review = FlashcardReview::create([
      'user_id' => $user->id,
      'study_material_id' => $studyMaterial->id,
      'rating' => 'good',
      'reviewed_at' => now(),
      'study_time' => 120 // 2 minutes
    ]);

    // Create a user goal
    UserGoal::create([
      'user_id' => $user->id,
      'daily_goal' => 50,
      'goal_type' => 'cards_studied',
      'description' => 'Study cards daily'
    ]);

    // Mock the Supabase middleware
    $this->actingAs($user);

    // Make request to dashboard endpoint
    $response = $this->getJson('/api/dashboard');

    $response->assertStatus(200)
      ->assertJsonStructure([
        'user' => [
          'id',
          'name',
          'email',
          'user_type',
          'display_name',
          'user_tag'
        ],
        'metrics' => [
          'cards_studied_today',
          'current_streak',
          'overall_progress',
          'study_time'
        ],
        'recent_decks' => [
          '*' => ['name', 'card_count', 'last_studied', 'progress']
        ],
        'todays_goal' => [
          'studied',
          'goal',
          'remaining',
          'progress_percentage',
          'goal_type',
          'goal_description',
          'is_completed',
          'message'
        ]
      ]);

    // Verify specific values
    $response->assertJson([
      'user' => [
        'name' => $user->name,
        'user_type' => 'student',
        'user_tag' => 'Student'
      ],
      'metrics' => [
        'cards_studied_today' => 1,
        'current_streak' => 1,
        'overall_progress' => 100,
        'study_time' => '2m'
      ],
      'todays_goal' => [
        'studied' => 1,
        'goal' => 50,
        'remaining' => 49,
        'is_completed' => false
      ]
    ]);
  }
}
