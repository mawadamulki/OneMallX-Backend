<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_subscription_extension_requests', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('storeSubscriptionID');
            $table->foreign('storeSubscriptionID', 'sse_req_store_sub_fk')
                ->references('id')
                ->on('store_subscriptions')
                ->cascadeOnDelete();

            $table->text('applicantNote')->nullable();

            $table->unsignedBigInteger('requestedByUserID')->nullable();
            $table->foreign('requestedByUserID', 'sse_req_requester_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->string('status')->default('pending');

            $table->unsignedBigInteger('reviewedByUserID')->nullable();
            $table->foreign('reviewedByUserID', 'sse_req_reviewer_fk')
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
        Schema::dropIfExists('store_subscription_extension_requests');
    }
};
