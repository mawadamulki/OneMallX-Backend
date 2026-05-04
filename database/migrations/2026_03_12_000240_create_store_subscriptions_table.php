<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_subscriptions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('storeID');
            $table->foreign('storeID')
                ->references('id')
                ->on('stores')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('storeSubscriptionPlanID');
            $table->foreign('storeSubscriptionPlanID')
                ->references('id')
                ->on('store_subscription_plans')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('planPriceID');
            $table->foreign('planPriceID')
                ->references('id')
                ->on('store_plan_prices')
                ->cascadeOnDelete();

            $table->timestamp('startDate')->useCurrent();
            $table->timestamp('endDate')->nullable();

            $table->boolean('autoRenew')->default(false);

            $table->timestamps();
            $table->softDeletes();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_subscriptions');
    }
};

