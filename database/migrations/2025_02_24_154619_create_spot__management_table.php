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
            $table->string('type');
            $table->float('price_per_hour');
            $table->float('reservation_fees')->default(0);
            $table->integer('time_restriction')->default(0);
            $table->integer('points_per_hour');
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
