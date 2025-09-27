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
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();

            // Broj / kod naloga (MVP: jednostavan broj; generisat ćemo u kodu)
            $table->string('number')->unique(); // npr. "BOR-000123" ili "WO-20250925-0001"

            // Vezanost
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bike_id')->constrained()->cascadeOnDelete();

            // Dodjela i status
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('received');

            // Osnovna vremena (detaljnije praćenje dodajemo kasnije)
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            // Zbrojeno trajanje (min) — kasnije možemo preći na log tabelu
            $table->unsignedInteger('total_elapsed_minutes')->default(0);

            // Dodatno
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['location_id', 'status']);
            $table->index(['assigned_user_id']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
