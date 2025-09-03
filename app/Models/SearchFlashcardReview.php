<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SearchFlashcardReview extends Model
{
  use HasFactory;

  protected $fillable = [
    'user_id',
    'search_id',
    'flashcard_id',
    'rating',
    'reviewed_at',
    'session_id',
    'study_time',
  ];

  protected $casts = [
    'reviewed_at' => 'datetime',
    'study_time' => 'integer',
  ];

  public function search()
  {
    return $this->belongsTo(SearchFlashcardSearch::class, 'search_id');
  }

  public function flashcard()
  {
    return $this->belongsTo(SearchFlashcardResult::class, 'flashcard_id');
  }
}
