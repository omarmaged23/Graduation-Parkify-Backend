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
        Schema::create('reservation__blockers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservable_spot_id')->constrained('reservable__spots')->cascadeOnDelete()->cascadeOnUpdate();
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation__blockers');
    }
};
