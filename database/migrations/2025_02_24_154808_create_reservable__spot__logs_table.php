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
        Schema::create('reservable__spot__logs', function (Blueprint $table) {
            $table->id();
            $table->string('license_plate');
            $table->float('invoice_price')->nullable();
            $table->boolean('is_payed')->default(0);
            $table->timestamp('entered_at');
            $table->timestamp('exited_at')->nullable();
            $table->foreignId('reservable_spot_id')->constrained('reservable__spots')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservable__spot__logs');
    }
};
