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
        Schema::create('room', function (Blueprint $table) {
            $table->id();
            $table->string('RoomNumber')->unique();
            $table->enum('RoomType', ['Single', 'double', 'twin', 'family', 'suite']);
            $table->integer('Capacity');
            $table->enum('Status', ['ready', 'maintenance']);
            $table->string('imageShowRoom')->nullable();
            $table->decimal('defaultPrice', 12, 2);
            $table->decimal('defaultExtraBedPrice', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room');
    }
};
