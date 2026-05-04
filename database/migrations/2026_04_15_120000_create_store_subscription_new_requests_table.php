<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_subscription_new_requests', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('storeSubscriptionID');
            $table->foreign('storeSubscriptionID', 'ssn_req_store_sub_fk')
                ->references('id')
                ->on('store_subscriptions')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('requestedStoreSubscriptionPlanID');
            $table->foreign('requestedStoreSubscriptionPlanID', 'ssn_req_plan_fk')
                ->references('id')
                ->on('store_subscription_plans')
                ->restrictOnDelete();

            $table->unsignedBigInteger('requestedPlanPriceID');
            $table->foreign('requestedPlanPriceID', 'ssn_req_price_fk')
                ->references('id')
                ->on('store_plan_prices')
                ->restrictOnDelete();

            $table->text('applicantNote')->nullable();

            $table->unsignedBigInteger('requestedByUserID')->nullable();
            $table->foreign('requestedByUserID', 'ssn_req_requester_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->string('status')->default('pending');

            $table->unsignedBigInteger('reviewedByUserID')->nullable();
            $table->foreign('reviewedByUserID', 'ssn_req_reviewer_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->timestamp('reviewedAt')->nullable();
            $table->text('rejectionReason')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_subscription_new_requests');
    }
};
