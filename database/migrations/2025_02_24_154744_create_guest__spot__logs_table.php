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
        Schema::create('guest__spot__logs', function (Blueprint $table) {
            $table->id();
            $table->string('guest_plate');
            $table->float('invoice_price')->nullable();
            $table->timestamp('entered_at');
            $table->timestamp('exited_at')->nullable();
            $table->boolean('is_payed')->default(0);
            $table->text('qr_payment')->nullable();
            $table->foreignId('public_spot_id')->constrained('public__spots')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guest__spot__logs');
    }
};
