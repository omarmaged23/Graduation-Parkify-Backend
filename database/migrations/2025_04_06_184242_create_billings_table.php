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
        Schema::create('billings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('license_plate')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('EGP');
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->string('method'); // card/wallet
            $table->string('paymob_order_id')->nullable();
            $table->string('transaction_id')->nullable();
            $table->timestamp('completed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing');
    }
};
