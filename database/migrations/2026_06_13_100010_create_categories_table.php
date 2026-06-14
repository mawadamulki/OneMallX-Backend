<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('storeID')->nullable();
            $table->unsignedBigInteger('parentID')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('sortOrder')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('storeID')
                ->references('id')
                ->on('stores')
                ->cascadeOnDelete();

            $table->foreign('parentID')
                ->references('id')
                ->on('categories')
                ->nullOnDelete();

            $table->unique(['storeID', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
