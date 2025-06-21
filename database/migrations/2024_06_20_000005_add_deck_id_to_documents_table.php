<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up()
  {
    Schema::table('documents', function (Blueprint $table) {
      $table->foreignId('deck_id')->nullable()->after('user_id')->constrained('decks')->onDelete('set null');
    });
  }

  public function down()
  {
    Schema::table('documents', function (Blueprint $table) {
      $table->dropForeign(['deck_id']);
      $table->dropColumn('deck_id');
    });
  }
};
