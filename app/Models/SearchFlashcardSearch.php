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
        return $this->studySessions()->exists()
            || SearchFlashcardReview::where('search_id', $this->id)
                ->where('user_id', $this->user_id)
                ->exists();
    }

    /**
     * Get study statistics
     */
    public function getStudyStatsAttribute(): array
    {
        $sessions = $this->studySessions;
        $totalFlashcards = $this->flashcards_count;
        $reviewsQuery = SearchFlashcardReview::where('search_id', $this->id)
            ->where('user_id', $this->user_id);

        $reviewedFlashcards = (clone $reviewsQuery)
            ->distinct()
            ->count('flashcard_id');

        $latestReviews = collect();
        if ($reviewedFlashcards > 0) {
            $latestReviews = (clone $reviewsQuery)
                ->whereRaw('id IN (
                    SELECT MAX(id) FROM search_flashcard_reviews
                    WHERE user_id = ? AND search_id = ?
                    GROUP BY flashcard_id
                )', [$this->user_id, $this->id])
                ->get(['rating']);
        }

        if ($sessions->isEmpty() && $reviewedFlashcards === 0) {
            return [
                'total_sessions' => 0,
                'total_studied' => 0,
                'total_correct' => 0,
                'total_incorrect' => 0,
                'average_score' => 0,
                'completion_percentage' => 0,
                'total_flashcards' => $totalFlashcards,
                'reviewed_flashcards' => 0,
                'mastered_flashcards' => 0,
                'needs_review_count' => 0,
            ];
        }

        if ($reviewedFlashcards > 0) {
            $totalStudied = $reviewedFlashcards;
            $totalCorrect = $latestReviews->whereIn('rating', ['good', 'easy'])->count();
            $totalIncorrect = $latestReviews->whereIn('rating', ['again', 'hard'])->count();
        } else {
            $latestSession = $sessions->sortByDesc('started_at')->first();
            $totalStudied = (int) ($latestSession->studied_flashcards ?? 0);
            $totalCorrect = (int) ($latestSession->correct_answers ?? 0);
            $totalIncorrect = (int) ($latestSession->incorrect_answers ?? 0);
        }

        $averageScore = $totalStudied > 0 ? round(($totalCorrect / $totalStudied) * 100, 2) : 0;
        $completionPercentage = $totalFlashcards > 0
            ? round(min(100, ($totalStudied / $totalFlashcards) * 100), 2)
            : 0;
        $reviewSessionCount = (clone $reviewsQuery)
            ->whereNotNull('session_id')
            ->distinct()
            ->count('session_id');

        return [
            'total_sessions' => max($sessions->count(), $reviewSessionCount),
            'total_studied' => $totalStudied,
            'total_correct' => $totalCorrect,
            'total_incorrect' => $totalIncorrect,
            'average_score' => $averageScore,
            'completion_percentage' => $completionPercentage,
            'total_flashcards' => $totalFlashcards,
            'reviewed_flashcards' => $totalStudied,
            'mastered_flashcards' => $totalCorrect,
            'needs_review_count' => $totalIncorrect,
        ];
    }
}
