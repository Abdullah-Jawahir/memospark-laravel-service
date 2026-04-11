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
      $table->string('type'); // 'flashcard' for Q&A, 'quiz' for quizzes
      $table->json('content'); // Store question, answer, options, difficulty, etc.
      $table->string('language')->default('en');
      $table->timestamps();
    });
  }

  public function down()
  {
    Schema::dropIfExists('study_materials');
  }
};
