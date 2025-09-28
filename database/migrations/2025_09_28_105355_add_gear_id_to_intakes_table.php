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
        Schema::table('intakes', function (Blueprint $table) {
            if (!Schema::hasColumn('intakes', 'gear_id')) {
                $table->unsignedBigInteger('gear_id')->nullable()->after('customer_id')->index();

                // (opciono)
                // $table->foreign('gear_id')->references('id')->on('gears')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('intakes', function (Blueprint $table) {
            if (Schema::hasColumn('intakes', 'gear_id')) {
                // $table->dropForeign(['gear_id']);
                $table->dropColumn('gear_id');
            }
        });
    }
};
