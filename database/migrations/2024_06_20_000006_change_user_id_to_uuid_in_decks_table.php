<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up()
  {
    Schema::table('decks', function (Blueprint $table) {
      $table->dropForeign(['user_id']);
      $table->dropColumn('user_id');
    });
    Schema::table('decks', function (Blueprint $table) {
      $table->uuid('user_id')->after('id');
      // Foreign key constraint can be added if users.id is uuid
      // $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
    });
  }

  public function down()
  {
    Schema::table('decks', function (Blueprint $table) {
      $table->dropColumn('user_id');
      $table->foreignId('user_id')->constrained()->onDelete('cascade');
    });
  }
};
