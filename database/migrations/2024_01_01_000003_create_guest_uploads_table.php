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
      $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
      $table->timestamps();

      $table->unique(['guest_identifier', 'identifier_type']);
    });
  }

  public function down()
  {
    Schema::dropIfExists('guest_uploads');
  }
};
