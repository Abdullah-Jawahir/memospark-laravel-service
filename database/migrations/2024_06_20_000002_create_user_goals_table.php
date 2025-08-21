<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up()
  {
    Schema::create('user_goals', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained()->onDelete('cascade');
      $table->integer('daily_goal')->default(50);
      $table->timestamps();
    });
  }

  public function down()
  {
    Schema::dropIfExists('user_goals');
  }
};
