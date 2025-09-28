<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('intakes')) return;
        if (! Schema::hasColumn('intakes', 'bike_id')) return;

        $fk = DB::selectOne("
            SELECT CONSTRAINT_NAME AS name
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'intakes'
              AND COLUMN_NAME = 'bike_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
            LIMIT 1
        ");
        if ($fk && isset($fk->name)) {
            DB::statement("ALTER TABLE `intakes` DROP FOREIGN KEY `{$fk->name}`");
        }

        $indexes = DB::select("
            SELECT DISTINCT INDEX_NAME AS name
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'intakes'
              AND COLUMN_NAME = 'bike_id'
        ");
        foreach ($indexes as $idx) {
            if ($idx->name !== 'PRIMARY') {
                DB::statement("ALTER TABLE `intakes` DROP INDEX `{$idx->name}`");
            }
        }

        DB::statement("ALTER TABLE `intakes` DROP COLUMN `bike_id`");
    }

    public function down(): void
    {
        if (Schema::hasTable('intakes') && ! Schema::hasColumn('intakes', 'bike_id')) {
            DB::statement("ALTER TABLE `intakes` ADD COLUMN `bike_id` BIGINT UNSIGNED NULL");
        }
    }
};
