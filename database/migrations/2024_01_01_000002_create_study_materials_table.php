<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up()
  {
    Schema::create('study_materials', function (Blueprint $table) {
      $table->id();
      $table->foreignId('document_id')->constrained();
      $table->enum('type', ['flashcard', 'quiz', 'exercise']);
      $table->json('content');
      $table->enum('language', ['en', 'si', 'ta']);
      $table->timestamps();
    });
  }

  public function down()
  {
    Schema::dropIfExists('study_materials');
  }
};
