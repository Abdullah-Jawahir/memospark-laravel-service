<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up()
  {
    Schema::create('guest_uploads', function (Blueprint $table) {
      $table->id();
      $table->string('guest_identifier'); // IP address or session ID
      $table->string('identifier_type'); // 'ip' or 'session'
      $table->unsignedBigInteger('document_id');
      $table->timestamps();

      $table->unique(['guest_identifier', 'identifier_type']);
      $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
    });
  }

  public function down()
  {
    Schema::dropIfExists('guest_uploads');
  }
};
