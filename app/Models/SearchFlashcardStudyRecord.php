<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchFlashcardStudyRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'study_session_id',
        'flashcard_id',
        'result',
        'time_spent',
        'attempts',
        'answered_at'
    ];

    protected $casts = [
        'time_spent' => 'integer',
        'attempts' => 'integer',
        'answered_at' => 'datetime'
    ];

    /**
     * Get the study session this record belongs to
     */
    public function studySession(): BelongsTo
    {
        return $this->belongsTo(SearchFlashcardStudySession::class, 'study_session_id');
    }

    /**
     * Get the flashcard this record is for
     */
    public function flashcard(): BelongsTo
    {
        return $this->belongsTo(SearchFlashcardResult::class, 'flashcard_id');
    }

    /**
     * Check if the answer was correct
     */
    public function isCorrect(): bool
    {
        return $this->result === 'correct';
    }

    /**
     * Check if the answer was incorrect
     */
    public function isIncorrect(): bool
    {
        return $this->result === 'incorrect';
    }

    /**
     * Check if the flashcard was skipped
     */
    public function wasSkipped(): bool
    {
        return $this->result === 'skipped';
    }

    /**
     * Get the user who studied this flashcard
     */
    public function getUserAttribute()
    {
        return $this->studySession->user_id;
    }
}
