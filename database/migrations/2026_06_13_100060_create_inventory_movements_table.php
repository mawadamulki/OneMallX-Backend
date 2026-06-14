<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('productVariantID');
            $table->unsignedBigInteger('storeID');
            $table->string('type');
            $table->integer('quantityChange');
            $table->integer('quantityAfter');
            $table->string('referenceType')->nullable();
            $table->unsignedBigInteger('referenceID')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('createdBy')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('productVariantID')
                ->references('id')
                ->on('product_variants')
                ->cascadeOnDelete();

            $table->foreign('storeID')
                ->references('id')
                ->on('stores')
                ->cascadeOnDelete();

            $table->foreign('createdBy')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['productVariantID', 'created_at']);
            $table->index(['referenceType', 'referenceID']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
