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
        Schema::create('dashboard', function (Blueprint $table) {
            $table->id();
            $table->string('location')->default('all');
            $table->float('total_profit');
            $table->integer('available_public_spots');
            $table->integer('available_reservable_spots');
            $table->integer('total_users');
            $table->json('popular_public_spots');
            $table->json('popular_reservable_spots');
            $table->integer('active_user_accounts');
            $table->integer('inactive_user_accounts');
            $table->json('popular_gifts');
            $table->json('monthly_profit');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard');
    }
};
