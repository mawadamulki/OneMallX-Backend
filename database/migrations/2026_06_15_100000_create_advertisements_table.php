<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('storeID')->nullable();
            $table->unsignedBigInteger('serviceID')->nullable();
            $table->string('title');
            $table->string('image');
            $table->enum('targetType', ['store', 'product', 'service', 'service_item']);
            $table->unsignedBigInteger('targetID');
            $table->enum('placement', ['home', 'deals'])->default('deals');
            $table->date('startDate');
            $table->date('endDate');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('storeID')
                ->references('id')
                ->on('stores')
                ->cascadeOnDelete();

            $table->foreign('serviceID')
                ->references('id')
                ->on('services')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertisements');
    }
};
