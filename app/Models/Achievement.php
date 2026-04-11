<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    protected $fillable = [
        'name',
        'description',
        'icon',
        'criteria',
        'points',
    ];

    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'user_achievements',
            'achievement_id',
            'user_id',
            'id',
            'supabase_user_id'
        )->withTimestamps()->withPivot('achieved_at');
    }
}
