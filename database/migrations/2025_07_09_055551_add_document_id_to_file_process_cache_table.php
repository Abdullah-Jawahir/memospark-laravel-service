<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('file_process_cache', function (Blueprint $table) {
            $table->foreignId('document_id')->nullable()->after('difficulty')->constrained('documents')->cascadeOnDelete();

            // Drop the old unique constraint that included card_types_hash
            $table->dropUnique('file_process_cache_unique');

            // Add new unique constraint without card_types_hash
            $table->unique(['file_hash', 'language', 'difficulty'], 'file_process_cache_unique_new');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('file_process_cache', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->dropColumn('document_id');

            // Drop the new unique constraint
            $table->dropUnique('file_process_cache_unique_new');

            // Restore the old unique constraint
            $table->unique(['file_hash', 'language', 'difficulty', 'card_types_hash'], 'file_process_cache_unique');
        });
    }
};
