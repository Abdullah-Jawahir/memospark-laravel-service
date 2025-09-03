<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GoalType extends Model
{
  use HasFactory;

  protected $fillable = [
    'id',
    'name',
    'description',
    'unit',
    'category',
    'is_active',
    'default_value',
    'min_value',
    'max_value'
  ];

  protected $casts = [
    'is_active' => 'boolean',
    'default_value' => 'integer',
    'min_value' => 'integer',
    'max_value' => 'integer'
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

  public function userGoals()
  {
    return $this->hasMany(UserGoal::class);
  }
}
