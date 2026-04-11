<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoalType extends Model
{
  use HasFactory;

  protected $fillable = [
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

  public function userGoals()
  {
    return $this->hasMany(UserGoal::class);
  }
}
