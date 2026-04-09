<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                "DELETE current
                 FROM file_process_cache AS current
                 INNER JOIN file_process_cache AS newer
                    ON current.file_hash = newer.file_hash
                   AND current.language = newer.language
                   AND current.difficulty = newer.difficulty
                   AND current.id < newer.id"
            );

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement(
                "DELETE FROM file_process_cache AS current
                 USING file_process_cache AS newer
                 WHERE current.file_hash = newer.file_hash
                   AND current.language = newer.language
                   AND current.difficulty = newer.difficulty
                   AND current.id < newer.id"
            );

            return;
        }

        // SQLite and other drivers.
        DB::statement(
            "DELETE FROM file_process_cache
             WHERE EXISTS (
               SELECT 1
               FROM file_process_cache AS newer
               WHERE newer.file_hash = file_process_cache.file_hash
                 AND newer.language = file_process_cache.language
                 AND newer.difficulty = file_process_cache.difficulty
                 AND newer.id > file_process_cache.id
             )"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed for cleanup
    }
};
