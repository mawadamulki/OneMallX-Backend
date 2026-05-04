<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('basket_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('basketID');
            $table->unsignedBigInteger('productID');
            $table->integer('quantity');
            $table->integer('unitPrice'); // unit price at time of adding
            $table->timestamps();

            $table->foreign('basketID')
                ->references('id')
                ->on('baskets')
                ->cascadeOnDelete();

            $table->foreign('productID')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('basket_products');
    }
};

