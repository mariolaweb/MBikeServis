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
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // npr. "Servis Borik"
            $table->string('code')->unique();       // kratki kod: "BOR", "CTR"
            // Kontakt i adresa (MVP, jednostavno):
            $table->string('phone')->nullable();    // E.164 preporuka (validacija u formi)
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->default('Banja Luka');
            $table->string('postal_code')->nullable();
            $table->string('country')->default('BA');

            // Geopozicija (opciono ali korisno):
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            // ERP/POS hookovi:
            $table->string('erp_warehouse_code')->nullable();
            $table->string('pos_identifier')->nullable();

            // Lokalna numeracija računa:
            $table->string('invoice_prefix')->default('CTR');
            $table->unsignedInteger('invoice_counter')->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
