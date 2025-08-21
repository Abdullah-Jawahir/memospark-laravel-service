<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudyMaterial extends Model
{
  use HasFactory;

  protected $fillable = [
    'document_id',
    'type',
    'content',
    'language',
  ];

  protected $casts = [
    'content' => 'array',
  ];

  public function document()
  {
    return $this->belongsTo(Document::class);
  }

  public function reviews()
  {
    return $this->hasMany(FlashcardReview::class);
  }
}
