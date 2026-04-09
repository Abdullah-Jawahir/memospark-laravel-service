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
      $table->string('user_id')->nullable();
      $table->string('original_filename');
      $table->string('storage_path');
      $table->string('file_type');
      $table->string('language');
      $table->string('status');
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
