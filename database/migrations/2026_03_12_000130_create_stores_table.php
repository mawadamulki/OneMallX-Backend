<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('storeOwnerID'); // references users.id
            $table->unsignedBigInteger('areaID');       // references areas.id
            $table->text('description')->nullable();
            $table->string('status')->nullable();
            $table->string('paymentAccount')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('storeOwnerID')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('areaID')
                ->references('id')
                ->on('areas')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};

