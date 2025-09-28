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
        Schema::create('wo_items', function (Blueprint $table) {
            $table->id();

            // Obavezno
            $table->unsignedBigInteger('work_order_id')->index();

            // Šta je stavka
            $table->string('sku', 100)->nullable()->index();   // šifra iz ERP-a (ako postoji)
            $table->string('name');                             // naziv u trenutku dodavanja
            $table->enum('kind', ['part', 'service'])->nullable(); // opcionalno: tip

            // Količine i cijene
            $table->decimal('qty', 12, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);

            // Audit / mjeko uklanjanje stavke (bez brisanja reda)
            $table->unsignedBigInteger('added_by')->nullable()->index();
            $table->timestamp('removed_at')->nullable()->index();
            $table->unsignedBigInteger('removed_by')->nullable()->index();

            $table->timestamps();

            // (Opcionalno – uključi kad ti redoslijed migracija i FK-ovi sigurno prolaze)
            // $table->foreign('work_order_id')->references('id')->on('work_orders')->onDelete('cascade');
            // $table->foreign('added_by')->references('id')->on('users')->nullOnDelete();
            // $table->foreign('removed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wo_items');
    }
};
