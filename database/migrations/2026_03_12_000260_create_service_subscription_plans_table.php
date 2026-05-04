<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('floorID')->nullable();
            $table->foreign('floorID')
                ->references('id')
                ->on('floors')
                ->cascadeOnDelete();
            $table->integer('serviceSpace');
            $table->integer('adsNumber');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_subscription_plans');
    }
};

