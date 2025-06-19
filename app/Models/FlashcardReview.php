<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FlashcardReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'study_material_id',
        'rating',
        'reviewed_at',
        'session_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function studyMaterial()
    {
        return $this->belongsTo(StudyMaterial::class);
    }
}
