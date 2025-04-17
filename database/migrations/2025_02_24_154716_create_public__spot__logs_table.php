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
        Schema::create('public__spot__logs', function (Blueprint $table) {
            $table->id();
            $table->string('license_plate');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->float('invoice_price')->nullable();
            $table->boolean('is_payed')->default(0);
            $table->timestamp('entered_at');
            $table->timestamp('exited_at')->nullable();
//            $table->foreignId('public_spot_id')->constrained('public__spots')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public__spot__logs');
    }
};
