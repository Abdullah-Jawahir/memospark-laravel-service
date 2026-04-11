<?php

namespace Tests\Feature;

use App\Models\Deck;
use App\Models\GoalType;
use App\Models\User;
use App\Models\UserGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminControllerIdConsistencyTest extends TestCase
{
  use RefreshDatabase;

  public function test_recent_activity_resolves_deck_user_by_supabase_user_id(): void
  {
    $admin = User::factory()->create([
      'user_type' => 'admin',
      'supabase_user_id' => 'aaaaaaaa-1111-2222-3333-444444444444',
    ]);

    $student = User::factory()->create([
      'user_type' => 'student',
      'supabase_user_id' => 'bbbbbbbb-1111-2222-3333-444444444444',
    ]);

    Deck::create([
      'user_id' => $student->supabase_user_id,
      'name' => 'UUID Deck',
    ]);

    $this->actingAs($admin);

    $response = $this->getJson('/api/admin/recent-activity');

    $response->assertStatus(200)
      ->assertJsonFragment([
        'type' => 'deck_creation',
        'user' => $student->email,
      ]);
  }

  public function test_goal_overview_and_goal_statistics_work_with_uuid_user_goals(): void
  {
    $admin = User::factory()->create([
      'user_type' => 'admin',
      'supabase_user_id' => 'cccccccc-1111-2222-3333-444444444444',
    ]);

    $student = User::factory()->create([
      'user_type' => 'student',
      'supabase_user_id' => 'dddddddd-1111-2222-3333-444444444444',
    ]);

    UserGoal::create([
      'user_id' => $student->supabase_user_id,
      'daily_goal' => 40,
      'goal_type' => 'cards_studied',
      'description' => 'Test goal',
    ]);

    $this->actingAs($admin);

    $overview = $this->getJson('/api/admin/goals/overview');
    $overview->assertStatus(200)
      ->assertJsonPath('total_users_with_goals', 1)
      ->assertJsonPath('total_users_without_goals', 1);

    $statistics = $this->getJson('/api/admin/goals/statistics');
    $statistics->assertStatus(200)
      ->assertJsonStructure([
        'goals_by_user_type',
        'goal_trends',
        'active_users',
      ]);
  }

  public function test_create_user_goal_accepts_local_user_id_and_persists_supabase_user_id(): void
  {
    $admin = User::factory()->create([
      'user_type' => 'admin',
      'supabase_user_id' => 'eeeeeeee-1111-2222-3333-444444444444',
    ]);

    $student = User::factory()->create([
      'user_type' => 'student',
      'supabase_user_id' => 'ffffffff-1111-2222-3333-444444444444',
    ]);

    $goalType = GoalType::create([
      'name' => 'Admin Goal Type',
      'description' => 'Used in admin create-user-goal regression test',
      'unit' => 'cards',
      'category' => 'study',
      'is_active' => true,
      'default_value' => 25,
      'min_value' => 1,
      'max_value' => 100,
    ]);

    $this->actingAs($admin);

    $response = $this->postJson('/api/admin/user-goals', [
      'user_id' => (string) $student->id,
      'goal_type_id' => $goalType->id,
      'target_value' => 35,
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('user_goals', [
      'user_id' => $student->supabase_user_id,
      'goal_type_id' => $goalType->id,
      'target_value' => 35,
    ]);
  }
}
