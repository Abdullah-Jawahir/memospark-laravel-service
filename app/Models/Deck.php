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
        return $this->hasMany(StudyMaterial::class, 'document_id', 'id');
    }
}
