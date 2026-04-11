<?php

namespace Tests\Feature;

use App\Models\Deck;
use App\Models\Document;
use App\Models\StudyMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashcardReviewControllerTest extends TestCase
{
  use RefreshDatabase;

  public function test_store_and_index_use_local_user_id_for_flashcard_reviews(): void
  {
    $user = User::factory()->create([
      'user_type' => 'student',
      'supabase_user_id' => '12345678-90ab-cdef-1234-567890abcdef',
    ]);

    $deck = Deck::create([
      'user_id' => $user->supabase_user_id,
      'name' => 'Review Deck',
    ]);

    $document = Document::create([
      'user_id' => $user->supabase_user_id,
      'deck_id' => $deck->id,
      'original_filename' => 'flashcards.pdf',
      'storage_path' => 'tests/flashcards.pdf',
      'file_type' => 'pdf',
      'language' => 'en',
      'status' => 'completed',
    ]);

    $studyMaterial = StudyMaterial::create([
      'document_id' => $document->id,
      'type' => 'flashcard',
      'content' => ['question' => 'Q', 'answer' => 'A'],
      'metadata' => ['question' => 'Q', 'answer' => 'A'],
    ]);

    $this->actingAs($user);

    $storeResponse = $this->postJson('/api/flashcard-reviews', [
      'study_material_id' => $studyMaterial->id,
      'rating' => 'good',
      'study_time' => 45,
      'session_id' => 'session-1',
    ]);

    $storeResponse->assertStatus(201)
      ->assertJsonPath('user_id', $user->id);

    $this->assertDatabaseHas('flashcard_reviews', [
      'user_id' => $user->id,
      'study_material_id' => $studyMaterial->id,
      'rating' => 'good',
      'session_id' => 'session-1',
    ]);

    $indexResponse = $this->getJson('/api/flashcard-reviews');

    $indexResponse->assertStatus(200)
      ->assertJsonCount(1)
      ->assertJsonPath('0.user_id', $user->id)
      ->assertJsonPath('0.study_material_id', $studyMaterial->id);
  }
}
