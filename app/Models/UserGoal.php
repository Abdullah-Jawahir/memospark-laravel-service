<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserGoal extends Model
{
    protected $fillable = [
        'user_id',
        'daily_goal',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
