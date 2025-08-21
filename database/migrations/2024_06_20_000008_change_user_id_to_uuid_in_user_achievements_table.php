<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up()
  {
    Schema::table('user_achievements', function (Blueprint $table) {
      $table->dropForeign(['user_id']);
      $table->dropColumn('user_id');
    });
    Schema::table('user_achievements', function (Blueprint $table) {
      $table->uuid('user_id')->after('id');
    });
  }

  public function down()
  {
    Schema::table('user_achievements', function (Blueprint $table) {
      $table->dropColumn('user_id');
      $table->foreignId('user_id')->constrained()->onDelete('cascade');
    });
  }
};
