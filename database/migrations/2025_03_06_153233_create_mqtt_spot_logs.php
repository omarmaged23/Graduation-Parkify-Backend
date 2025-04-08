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
        Schema::create('mqtt_spot_logs', function (Blueprint $table) {
            $table->id();
            $table->string('license_plate');
            $table->string('location');
            $table->timestamp('entered_at');
            $table->timestamp('exited_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mqtt_spot_logs');
    }
};
