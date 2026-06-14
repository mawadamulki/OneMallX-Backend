<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('basket_products');

        Schema::create('basket_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('basketID');
            $table->string('lineType');
            $table->string('itemType');
            $table->unsignedBigInteger('itemID');
            $table->string('lineKey');
            $table->integer('quantity')->default(1);
            $table->integer('unitPrice');
            $table->unsignedBigInteger('employeeID')->nullable();
            $table->date('scheduledDate')->nullable();
            $table->time('scheduledTime')->nullable();
            $table->timestamps();

            $table->foreign('basketID')
                ->references('id')
                ->on('baskets')
                ->cascadeOnDelete();

            $table->foreign('employeeID')
                ->references('id')
                ->on('employees')
                ->nullOnDelete();

            $table->index(['basketID', 'lineType']);
            $table->index(['itemType', 'itemID']);
            $table->unique(['basketID', 'lineKey']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('basket_items');

        Schema::create('basket_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('basketID');
            $table->unsignedBigInteger('productID');
            $table->integer('quantity');
            $table->integer('unitPrice');
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
};
