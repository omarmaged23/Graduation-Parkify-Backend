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
        Schema::create('spot__management', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique();
            $table->float('price_per_hour');
            $table->float('additional_guest_fees')->default(10);
            $table->float('reservation_fees')->default(20);
            $table->integer('time_restriction')->default(60);
            $table->integer('points_per_hour')->default(20);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spot__management');
    }
};
