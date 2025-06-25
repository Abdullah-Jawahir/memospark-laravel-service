<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuestUpload extends Model
{
  use HasFactory;

  protected $fillable = [
    'guest_identifier',
    'identifier_type',
    'document_id'
  ];

  public function document()
  {
    return $this->belongsTo(Document::class);
  }

  public static function hasGuestUploaded($identifier, $type = 'ip')
  {
    return self::where('guest_identifier', $identifier)
      ->where('identifier_type', $type)
      ->exists();
  }

  public static function createGuestUpload($identifier, $documentId, $type = 'ip')
  {
    return self::create([
      'guest_identifier' => $identifier,
      'identifier_type' => $type,
      'document_id' => $documentId
    ]);
  }
}
