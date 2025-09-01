<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UserGoal extends Model
{
    protected $fillable = [
        'id',
        'user_id',
        'goal_type_id',
        'target_value',
        'current_value',
        'is_active',
        'daily_goal',      // Keep for backward compatibility
        'goal_type',       // Keep for backward compatibility
        'description',     // Keep for backward compatibility
    ];

    protected $casts = [
        'target_value' => 'integer',
        'current_value' => 'integer',
        'is_active' => 'boolean',
        'daily_goal' => 'integer'
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function goalType()
    {
        return $this->belongsTo(GoalType::class, 'goal_type_id');
    }
}
