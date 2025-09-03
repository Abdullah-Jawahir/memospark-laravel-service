<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SearchFlashcardStudySession extends Model
{
    use HasFactory;

    protected $fillable = [
        'search_id',
        'user_id',
        'started_at',
        'completed_at',
        'total_flashcards',
        'studied_flashcards',
        'correct_answers',
        'incorrect_answers',
        'study_data'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_flashcards' => 'integer',
        'studied_flashcards' => 'integer',
        'correct_answers' => 'integer',
        'incorrect_answers' => 'integer',
        'study_data' => 'array'
    ];

    /**
     * Get the search this study session belongs to
     */
    public function search(): BelongsTo
    {
        return $this->belongsTo(SearchFlashcardSearch::class, 'search_id');
    }

    /**
     * Get the study records for this session
     */
    public function studyRecords(): HasMany
    {
        return $this->hasMany(SearchFlashcardStudyRecord::class, 'study_session_id');
    }

    /**
     * Get the flashcards for this study session
     */
    public function flashcards()
    {
        return $this->search->flashcards;
    }

    /**
     * Calculate the completion percentage
     */
    public function getCompletionPercentageAttribute(): float
    {
        if ($this->total_flashcards === 0) {
            return 0;
        }
        return round(($this->studied_flashcards / $this->total_flashcards) * 100, 2);
    }

    /**
     * Calculate the accuracy percentage
     */
    public function getAccuracyPercentageAttribute(): float
    {
        $totalAnswered = $this->correct_answers + $this->incorrect_answers;
        if ($totalAnswered === 0) {
            return 0;
        }
        return round(($this->correct_answers / $totalAnswered) * 100, 2);
    }

    /**
     * Check if the session is completed
     */
    public function isCompleted(): bool
    {
        return !is_null($this->completed_at);
    }

    /**
     * Get the duration of the study session
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->started_at) {
            return null;
        }

        $endTime = $this->completed_at ?? now();
        return $this->started_at->diffInSeconds($endTime);
    }
}
