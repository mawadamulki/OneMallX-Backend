<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('storeID')->nullable();
            $table->string('name');
            $table->string('code');
            $table->unsignedInteger('sortOrder')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('storeID')
                ->references('id')
                ->on('stores')
                ->cascadeOnDelete();

            $table->unique(['storeID', 'code']);
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attributeID');
            $table->string('value');
            $table->unsignedInteger('sortOrder')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('attributeID')
                ->references('id')
                ->on('attributes')
                ->cascadeOnDelete();

            $table->unique(['attributeID', 'value']);
        });

        Schema::create('product_variant_attribute_value', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('productVariantID');
            $table->unsignedBigInteger('attributeValueID');
            $table->timestamps();

            $table->foreign('productVariantID')
                ->references('id')
                ->on('product_variants')
                ->cascadeOnDelete();

            $table->foreign('attributeValueID')
                ->references('id')
                ->on('attribute_values')
                ->cascadeOnDelete();

            $table->unique(['productVariantID', 'attributeValueID'], 'pvav_variant_value_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_attribute_value');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
    }
};
