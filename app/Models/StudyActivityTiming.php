<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudyActivityTiming extends Model
{
  use HasFactory;

  protected $fillable = [
    'session_id',
    'activity_type',
    'start_time',
    'end_time',
    'duration_seconds',
    'activity_details',
  ];

  protected $casts = [
    'start_time' => 'datetime',
    'end_time' => 'datetime',
    'activity_details' => 'array',
  ];

  public function sessionTiming()
  {
    return $this->belongsTo(StudySessionTiming::class, 'session_id', 'session_id');
  }
}
