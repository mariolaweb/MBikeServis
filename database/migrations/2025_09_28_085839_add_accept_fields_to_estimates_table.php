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
            // nullable jer predračun može postojati i prije prihvatanja
            $table->unsignedBigInteger('work_order_id')->nullable()->after('total');
            $table->unsignedBigInteger('accepted_by')->nullable()->after('work_order_id');
            $table->timestamp('accepted_at')->nullable()->after('accepted_by');

            // indeksi za brze upite
            $table->index('work_order_id', 'estimates_work_order_id_index');
            $table->index('accepted_at',   'estimates_accepted_at_index');

            // (opciono) FK-ovi — dodaj ako su ti tabele već tu i ne pravi problem s redoslijedom migracija
            // Ako dodaš FK, vodi računa o onDelete ponašanju prema tvojoj politici brisanja.
            // $table->foreign('work_order_id')->references('id')->on('work_orders')->onDelete('set null');
            // $table->foreign('accepted_by')->references('id')->on('users')->onDelete('set null');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            // ako si dodao FK-ove — prvo ih dropaj
            // $table->dropForeign(['work_order_id']);
            // $table->dropForeign(['accepted_by']);

            $table->dropIndex('estimates_work_order_id_index');
            $table->dropIndex('estimates_accepted_at_index');

            $table->dropColumn(['accepted_at', 'accepted_by', 'work_order_id']);

        });
    }
};
