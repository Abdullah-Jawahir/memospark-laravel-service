<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
  use HasFactory, SoftDeletes;

  protected $fillable = [
    'user_id',
    'original_filename',
    'storage_path',
    'file_type',
    'language',
    'status',
    'metadata'
  ];

  protected $casts = [
    'metadata' => 'array'
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }
}
