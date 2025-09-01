<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchFlashcardDifficultCard extends Model
{
  use HasFactory;

  protected $fillable = [
    'user_id',
    'search_id',
    'flashcard_id',
    'status',
    'marked_at',
    'reviewed_at',
    're_rated_at',
    'final_rating'
  ];

  protected $casts = [
    'marked_at' => 'datetime',
    'reviewed_at' => 'datetime',
    're_rated_at' => 'datetime'
  ];

  /**
   * Get the user who marked this card as difficult
   */
  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  /**
   * Get the search this difficult card belongs to
   */
  public function search(): BelongsTo
  {
    return $this->belongsTo(SearchFlashcardSearch::class, 'search_id');
  }

  /**
   * Get the flashcard that was marked as difficult
   */
  public function flashcard(): BelongsTo
  {
    return $this->belongsTo(SearchFlashcardResult::class, 'flashcard_id');
  }

  /**
   * Check if the card is currently marked as difficult
   */
  public function isCurrentlyDifficult(): bool
  {
    return $this->status === 'marked_difficult';
  }

  /**
   * Check if the card has been reviewed
   */
  public function hasBeenReviewed(): bool
  {
    return $this->status === 'reviewed';
  }

  /**
   * Check if the card has been re-rated
   */
  public function hasBeenReRated(): bool
  {
    return $this->status === 're_rated';
  }

  /**
   * Mark the card as reviewed
   */
  public function markAsReviewed(): void
  {
    $this->update([
      'status' => 'reviewed',
      'reviewed_at' => now()
    ]);
  }

  /**
   * Mark the card as re-rated with final rating
   */
  public function markAsReRated(string $finalRating): void
  {
    $this->update([
      'status' => 're_rated',
      're_rated_at' => now(),
      'final_rating' => $finalRating
    ]);
  }

  /**
   * Scope to get only currently difficult cards
   */
  public function scopeCurrentlyDifficult($query)
  {
    return $query->where('status', 'marked_difficult');
  }

  /**
   * Scope to get cards by user
   */
  public function scopeByUser($query, $userId)
  {
    return $query->where('user_id', $userId);
  }

  /**
   * Scope to get cards by search
   */
  public function scopeBySearch($query, $searchId)
  {
    return $query->where('search_id', $searchId);
  }
}
