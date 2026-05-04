<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('basketID');
            $table->unsignedBigInteger('userID');
            $table->string('status')->default('pending');
            $table->integer('totalPrice')->default(0);
            $table->timestamps();

            $table->foreign('basketID')
                ->references('id')
                ->on('baskets')
                ->cascadeOnDelete();

            $table->foreign('userID')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

