<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_category', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('productID');
            $table->unsignedBigInteger('categoryID');
            $table->timestamps();

            $table->foreign('productID')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();

            $table->foreign('categoryID')
                ->references('id')
                ->on('categories')
                ->cascadeOnDelete();

            $table->unique(['productID', 'categoryID']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_category');
    }
};
