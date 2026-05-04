<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_subscription_requests', function (Blueprint $table) {
            $table->id();

            $table->string('applicantName');
            $table->string('email');
            $table->string('password');
            $table->string('phoneNumber');

            $table->string('serviceName');
            $table->integer('price');
            $table->unsignedBigInteger('areaID');
            $table->foreign('areaID')
                ->references('id')
                ->on('areas')
                ->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->string('paymentAccount')->nullable();
            $table->time('openTime')->nullable();
            $table->time('closeTime')->nullable();
            $table->integer('duration')->nullable();
            $table->unsignedBigInteger('locationID')->nullable();
            $table->foreign('locationID')
                ->references('id')
                ->on('locations')
                ->nullOnDelete();
            $table->string('serviceStatus')->default('pending');
            $table->string('daysOfWeek')->nullable();

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

            $table->unsignedBigInteger('createdServiceID')->nullable();
            $table->foreign('createdServiceID')
                ->references('id')
                ->on('services')
                ->nullOnDelete();

            $table->unsignedBigInteger('createdSubscriptionID')->nullable();
            $table->foreign('createdSubscriptionID')
                ->references('id')
                ->on('service_subscriptions')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_subscription_requests');
    }
};
