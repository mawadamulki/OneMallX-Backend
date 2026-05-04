<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('serviceID');
            $table->foreign('serviceID')
                ->references('id')
                ->on('services')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('serviceSubscriptionPlanID');
            $table->foreign('serviceSubscriptionPlanID')
                ->references('id')
                ->on('service_subscription_plans')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('planPriceID');
            $table->foreign('planPriceID')
                ->references('id')
                ->on('service_plan_prices')
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
        Schema::dropIfExists('service_subscriptions');
    }
};

