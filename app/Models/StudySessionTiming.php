<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudySessionTiming extends Model
{
  use HasFactory;

  protected $fillable = [
    'session_id',
    'user_id',
    'total_study_time',
    'flashcard_time',
    'quiz_time',
    'exercise_time',
    'session_start',
    'session_end',
  ];

  protected $casts = [
    'session_start' => 'datetime',
    'session_end' => 'datetime',
  ];

  public function activities()
  {
    return $this->hasMany(StudyActivityTiming::class, 'session_id', 'session_id');
  }
}
