<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deck extends Model
{
    protected $fillable = [
        'user_id',
        'name',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function studyMaterials()
    {
        // Deck -> Document (deck_id) -> StudyMaterial (document_id)
        return $this->hasManyThrough(
            StudyMaterial::class,
            Document::class,
            'deck_id',      // Foreign key on documents table...
            'document_id',  // Foreign key on study_materials table...
            'id',           // Local key on decks table...
            'id'            // Local key on documents table...
        );
    }
}
