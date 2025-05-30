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
        Schema::create('payment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservation')->onDelete('cascade');
            $table->enum('payment_method', ['credit_card', 'debit_card', 'cash', 'online'])->default('credit_card');
            $table->enum('status', ['Paid', 'Unpaid', 'Failed', 'Refunded'])->default('Unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment');
    }
};
