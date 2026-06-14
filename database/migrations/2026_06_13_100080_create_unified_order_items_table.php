<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('order_products');

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('orderID');
            $table->string('lineType');
            $table->string('itemType');
            $table->unsignedBigInteger('itemID');
            $table->unsignedBigInteger('storeID')->nullable();
            $table->unsignedBigInteger('serviceID')->nullable();
            $table->integer('quantity')->default(1);
            $table->integer('unitPrice');
            $table->integer('lineTotal');
            $table->string('sku')->nullable();
            $table->string('itemName');
            $table->string('variantName')->nullable();
            $table->unsignedBigInteger('employeeID')->nullable();
            $table->date('scheduledDate')->nullable();
            $table->time('scheduledTime')->nullable();
            $table->timestamps();

            $table->foreign('orderID')
                ->references('id')
                ->on('orders')
                ->cascadeOnDelete();

            $table->foreign('storeID')
                ->references('id')
                ->on('stores')
                ->nullOnDelete();

            $table->foreign('serviceID')
                ->references('id')
                ->on('services')
                ->nullOnDelete();

            $table->foreign('employeeID')
                ->references('id')
                ->on('employees')
                ->nullOnDelete();

            $table->index(['orderID', 'lineType']);
            $table->index(['storeID']);
            $table->index(['serviceID']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');

        Schema::create('order_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('orderID');
            $table->unsignedBigInteger('productID');
            $table->integer('quantity');
            $table->integer('unitPrice');
            $table->timestamps();

            $table->foreign('orderID')
                ->references('id')
                ->on('orders')
                ->cascadeOnDelete();

            $table->foreign('productID')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();
        });
    }
};
