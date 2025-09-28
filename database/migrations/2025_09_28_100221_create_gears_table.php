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
        Schema::create('gears', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->index();

            // kategorija (bike, e-bike, scooter, ski, snowboard, …)
            $table->string('category', 50)->index();

            // zajednička polja
            $table->string('brand');
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable()->index();

            // specifična polja po kategoriji
            $table->json('attributes')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            // (opciono) FK, uključi kad je redoslijed migracija siguran
            // $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });

        // (OPCIJA) Primjeri “generated” kolona za često filtriranje:
        // ALTER TABLE gear ADD color VARCHAR(40) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.color'))) STORED;
        // ALTER TABLE gear ADD length_cm INT GENERATED ALWAYS AS (JSON_EXTRACT(attributes, '$.length_cm')) STORED;
        // + INDEX na te kolone

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gears');
    }
};
