<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Clean up duplicates by keeping only the most recent entry for each file_hash + language + difficulty combination
        DB::statement("
            DELETE f1 FROM file_process_cache f1
            INNER JOIN file_process_cache f2 
            WHERE f1.id < f2.id 
            AND f1.file_hash = f2.file_hash 
            AND f1.language = f2.language 
            AND f1.difficulty = f2.difficulty
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed for cleanup
    }
};
