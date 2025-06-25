<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up()
  {
    Schema::create('file_process_cache', function (Blueprint $table) {
      $table->id();
      $table->string('file_hash', 64);
      $table->string('language', 16)->default('en');
      $table->json('card_types')->nullable();
      $table->string('card_types_hash', 64);
      $table->string('difficulty', 16)->default('beginner');
      $table->json('result')->nullable();
      $table->enum('status', ['processing', 'done', 'failed'])->default('processing');
      $table->timestamps();
      $table->unique(['file_hash', 'language', 'difficulty', 'card_types_hash'], 'file_process_cache_unique');
    });
  }

  public function down()
  {
    Schema::dropIfExists('file_process_cache');
  }
};
