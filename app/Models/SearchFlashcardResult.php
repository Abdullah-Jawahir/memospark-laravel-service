<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SearchFlashcardResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'search_id',
        'question',
        'answer',
        'type',
        'difficulty',
        'order_index'
    ];

    protected $casts = [
        'order_index' => 'integer'
    ];

    /**
     * Get the search that generated this flashcard
     */
    public function search(): BelongsTo
    {
        return $this->belongsTo(SearchFlashcardSearch::class, 'search_id');
    }

    /**
     * Get the study records for this flashcard
     */
    public function studyRecords(): HasMany
    {
        return $this->hasMany(SearchFlashcardStudyRecord::class, 'flashcard_id');
    }

    /**
     * Get the user who owns this flashcard
     */
    public function user()
    {
        return $this->search->user_id;
    }

    /**
     * Get the topic this flashcard belongs to
     */
    public function getTopicAttribute(): string
    {
        return $this->search->topic;
    }
}
