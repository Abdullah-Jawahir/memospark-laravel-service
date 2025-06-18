<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up()
  {
    Schema::create('documents', function (Blueprint $table) {
      $table->id();
      $table->uuid('user_id')->nullable();
      $table->string('original_filename');
      $table->string('storage_path');
      $table->enum('file_type', ['pdf', 'docx', 'pptx', 'jpg', 'jpeg', 'png']);
      $table->enum('language', ['en', 'si', 'ta']);
      $table->enum('status', ['uploading', 'processing', 'completed', 'failed']);
      $table->json('metadata')->nullable();
      $table->timestamps();
      $table->softDeletes();
    });
  }

  public function down()
  {
    Schema::dropIfExists('documents');
  }
};
