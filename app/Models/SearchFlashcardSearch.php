<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SearchFlashcardSearch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'topic',
        'description',
        'difficulty',
        'requested_count',
        'job_id',
        'status',
        'error_message',
        'started_at',
        'completed_at'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'requested_count' => 'integer'
    ];

    /**
     * Get the flashcards generated for this search
     */
    public function flashcards(): HasMany
    {
        return $this->hasMany(SearchFlashcardResult::class, 'search_id')->orderBy('order_index');
    }

    /**
     * Get the study sessions for this search
     */
    public function studySessions(): HasMany
    {
        return $this->hasMany(SearchFlashcardStudySession::class, 'search_id');
    }

    /**
     * Get the most recent study session
     */
    public function latestStudySession()
    {
        return $this->hasOne(SearchFlashcardStudySession::class, 'search_id')->latest();
    }

    /**
     * Scope for completed searches
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for failed searches
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for searches by user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for recent searches
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get the total flashcards generated
     */
    public function getFlashcardsCountAttribute(): int
    {
        return $this->flashcards()->count();
    }

    /**
     * Check if the search has been studied
     */
    public function hasBeenStudied(): bool
    {
        return $this->studySessions()->exists();
    }

    /**
     * Get study statistics
     */
    public function getStudyStatsAttribute(): array
    {
        $sessions = $this->studySessions;

        if ($sessions->isEmpty()) {
            return [
                'total_sessions' => 0,
                'total_studied' => 0,
                'total_correct' => 0,
                'total_incorrect' => 0,
                'average_score' => 0
            ];
        }

        $totalStudied = $sessions->sum('studied_flashcards');
        $totalCorrect = $sessions->sum('correct_answers');
        $totalIncorrect = $sessions->sum('incorrect_answers');

        $averageScore = $totalStudied > 0 ? round(($totalCorrect / $totalStudied) * 100, 2) : 0;

        return [
            'total_sessions' => $sessions->count(),
            'total_studied' => $totalStudied,
            'total_correct' => $totalCorrect,
            'total_incorrect' => $totalIncorrect,
            'average_score' => $averageScore
        ];
    }
}
