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
        Schema::table('work_orders', function (Blueprint $table) {
            // QR/public link token (ULID/UUID). Nullable sada, jer prvo moramo backfill za postojeće redove.
            $table->string('public_token', 36)
              ->nullable()
              ->after('number');

            // Eventualno otkazivanje naloga
            $table->timestamp('cancelled_at')
              ->nullable()
              ->after('delivered_at');

            // Ručno gašenje javnog linka (opciono)
            $table->timestamp('public_token_disabled_at')
              ->nullable()
              ->after('cancelled_at');

            // Soft delete
            $table->softDeletes()->after('public_token_disabled_at');
        });

        // Indexi (poslije dodavanja kolona)
        Schema::table('work_orders', function (Blueprint $table) {
            $table->unique('public_token', 'work_orders_public_token_unique');
            $table->index('cancelled_at', 'work_orders_cancelled_at_idx');
            $table->index('deleted_at', 'work_orders_deleted_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            // skini indexe prije droppinga kolona
            if (Schema::hasColumn('work_orders', 'public_token')) {
                $table->dropUnique('work_orders_public_token_unique');
            }
            if (Schema::hasColumn('work_orders', 'cancelled_at')) {
                $table->dropIndex('work_orders_cancelled_at_idx');
            }
            if (Schema::hasColumn('work_orders', 'deleted_at')) {
                $table->dropIndex('work_orders_deleted_at_idx');
            }

            // drop kolone
            if (Schema::hasColumn('work_orders', 'public_token')) {
                $table->dropColumn('public_token');
            }
            if (Schema::hasColumn('work_orders', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
            if (Schema::hasColumn('work_orders', 'public_token_disabled_at')) {
                $table->dropColumn('public_token_disabled_at');
            }

            // soft deletes
            if (Schema::hasColumn('work_orders', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
