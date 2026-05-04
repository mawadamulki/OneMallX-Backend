<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_subscription_requests', function (Blueprint $table) {
            $table->id();

            $table->string('applicantName');
            $table->string('email');
            $table->string('password');
            $table->string('phoneNumber');

            $table->string('storeName');
            $table->unsignedBigInteger('areaID');
            $table->foreign('areaID')
                ->references('id')
                ->on('areas')
                ->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->string('storeStatus')->nullable();
            $table->string('paymentAccount')->nullable();

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

            $table->string('status')->default('pending');

            $table->unsignedBigInteger('reviewedByUserID')->nullable();
            $table->foreign('reviewedByUserID')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->timestamp('reviewedAt')->nullable();
            $table->text('rejectionReason')->nullable();

            $table->unsignedBigInteger('createdUserID')->nullable();
            $table->foreign('createdUserID')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->unsignedBigInteger('createdStoreID')->nullable();
            $table->foreign('createdStoreID')
                ->references('id')
                ->on('stores')
                ->nullOnDelete();

            $table->unsignedBigInteger('createdSubscriptionID')->nullable();
            $table->foreign('createdSubscriptionID')
                ->references('id')
                ->on('store_subscriptions')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_subscription_requests');
    }
};
