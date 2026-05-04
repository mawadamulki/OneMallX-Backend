<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('serviceID');
            $table->unsignedBigInteger('customerID'); // users.id
            $table->unsignedBigInteger('employeeID')->nullable();
            $table->date('date');
            $table->time('time')->nullable();
            $table->integer('entryNumber')->nullable();
            $table->string('status')->default('pending');
            $table->string('paymentStatus')->default('unpaid');
            $table->integer('totalPrice')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('serviceID')
                ->references('id')
                ->on('services')
                ->cascadeOnDelete();

            $table->foreign('customerID')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('employeeID')
                ->references('id')
                ->on('employees')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};

