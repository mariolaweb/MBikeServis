<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('work_orders')) return;
        if (! Schema::hasColumn('work_orders', 'bike_id')) return;

        // 1) Drop FK ako postoji
        $fk = DB::selectOne("
            SELECT CONSTRAINT_NAME AS name
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'work_orders'
              AND COLUMN_NAME = 'bike_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
            LIMIT 1
        ");
        if ($fk && isset($fk->name)) {
            DB::statement("ALTER TABLE `work_orders` DROP FOREIGN KEY `{$fk->name}`");
        }

        // 2) Drop index na bike_id ako postoji (NON-UNIQUE ili UNIQUE)
        $indexes = DB::select("
            SELECT DISTINCT INDEX_NAME AS name
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'work_orders'
              AND COLUMN_NAME = 'bike_id'
        ");
        foreach ($indexes as $idx) {
            if ($idx->name !== 'PRIMARY') {
                DB::statement("ALTER TABLE `work_orders` DROP INDEX `{$idx->name}`");
            }
        }

        // 3) Drop same kolone
        DB::statement("ALTER TABLE `work_orders` DROP COLUMN `bike_id`");
    }

    public function down(): void
    {
        // Ako se ikad vraća: kreiraj kolonu ponovo (bez FK jer je Gear zamjena)
        if (Schema::hasTable('work_orders') && ! Schema::hasColumn('work_orders', 'bike_id')) {
            DB::statement("ALTER TABLE `work_orders` ADD COLUMN `bike_id` BIGINT UNSIGNED NULL");
            // FK namjerno se ne vraća (prešli smo na gears)
        }
    }
};
