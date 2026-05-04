<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customerID'); // users.id
            $table->unsignedBigInteger('orderID'); // orders.id
            $table->unsignedBigInteger('methodID'); // payment_methods.id
            $table->integer('price');
            $table->timestamps();

            $table->foreign('customerID')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('orderID')
                ->references('id')
                ->on('orders')
                ->cascadeOnDelete();

            $table->foreign('methodID')
                ->references('id')
                ->on('payment_methods')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payments');
    }
};

