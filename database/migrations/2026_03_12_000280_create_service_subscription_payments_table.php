<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscriptionID'); // service_subscriptions.id
            $table->unsignedBigInteger('methodID'); // payment_methods.id
            $table->integer('price');
            $table->timestamps();

            $table->foreign('subscriptionID')
                ->references('id')
                ->on('service_subscriptions')
                ->cascadeOnDelete();

            $table->foreign('methodID')
                ->references('id')
                ->on('payment_methods')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_subscription_payments');
    }
};

