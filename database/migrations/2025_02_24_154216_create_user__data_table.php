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
        Schema::create('user__data', function (Blueprint $table) {
            $table->id();
            $table->string('national')->unique();
            $table->string('phone');
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(1);
            $table->float('balance')->default(0);
            $table->integer('points')->default(0);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user__data');
    }
};
