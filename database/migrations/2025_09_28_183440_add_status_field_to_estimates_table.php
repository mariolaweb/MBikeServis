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
        Schema::table('estimates', function (Blueprint $table) {
            $table->enum('status', ['pending','accepted','declined'])
              ->default('pending')
              ->after('received_at');

            // (preporučeno) indeks za brza filtriranja po WO i statusu
            $table->index(['work_order_id', 'status']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            if (Schema::hasColumn('estimates', 'status')) {
                $table->dropIndex(['work_order_id', 'status']);
                $table->dropColumn('status');
            }
        });
    }
};
