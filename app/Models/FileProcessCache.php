<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileProcessCache extends Model
{
  protected $table = 'file_process_cache';

  protected $fillable = [
    'file_hash',
    'language',
    'card_types',
    'card_types_hash',
    'difficulty',
    'result',
    'status',
    'document_id',
  ];

  protected $casts = [
    'card_types' => 'array',
    'result' => 'array',
  ];

  public function document()
  {
    return $this->belongsTo(Document::class);
  }
}
