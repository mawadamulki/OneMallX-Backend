<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('favorite_products');
        Schema::dropIfExists('favorites');

        Schema::create('favorite_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('userID');
            $table->unsignedBigInteger('productID');
            $table->timestamps();

            $table->foreign('userID')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('productID')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();

            $table->unique(['userID', 'productID']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorite_products');

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('userID');
            $table->timestamps();

            $table->foreign('userID')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

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
};
