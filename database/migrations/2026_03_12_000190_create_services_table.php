<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('serviceOwnerID'); // users.id
            $table->integer('price');
            $table->unsignedBigInteger('areaID'); // areas.id
            $table->text('description')->nullable();
            $table->string('paymentAccount')->nullable();
            $table->time('openTime')->nullable();
            $table->time('closeTime')->nullable();
            $table->integer('duration')->nullable(); // minutes
            $table->unsignedBigInteger('locationID')->nullable(); // locations.id
            $table->string('status')->default('pending');
            $table->string('daysOfWeek')->nullable(); // e.g. "sat,sun,mon"
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('serviceOwnerID')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('areaID')
                ->references('id')
                ->on('areas')
                ->cascadeOnDelete();

            $table->foreign('locationID')
                ->references('id')
                ->on('locations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};

