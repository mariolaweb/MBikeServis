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
        Schema::create('bikes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('brand');               // brend
            $table->string('model')->nullable();   // model
            $table->string('color')->nullable();
            $table->string('serial_number')->nullable(); // serijski broj (ako postoji)
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index('customer_id');   // kreirat će npr. "bikes_customer_id_index"
            $table->index('brand');         // "bikes_brand_index"
            $table->index('model');         // "bikes_model_index"

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bikes');
    }
};
