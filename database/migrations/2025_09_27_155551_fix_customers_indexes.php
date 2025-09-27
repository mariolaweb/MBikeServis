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
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_phone_email_index'); // naziv tvog indeksa
            $table->index('phone');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
        $table->dropIndex(['phone']);
        $table->dropIndex(['email']);
        $table->index(['phone', 'email'], 'customers_phone_email_index');
        });
    }
};
