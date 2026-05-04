<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorite_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('favoriteID');
            $table->unsignedBigInteger('productID');
            $table->timestamps();

            $table->foreign('favoriteID')
                ->references('id')
                ->on('favorites')
                ->cascadeOnDelete();

            $table->foreign('productID')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();

            $table->unique(['favoriteID', 'productID']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorite_products');
    }
};

