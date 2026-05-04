<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_subscription_extension_requests', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('serviceSubscriptionID');
            $table->foreign('serviceSubscriptionID', 'ssee_req_svc_sub_fk')
                ->references('id')
                ->on('service_subscriptions')
                ->cascadeOnDelete();

            $table->text('applicantNote')->nullable();

            $table->unsignedBigInteger('requestedByUserID')->nullable();
            $table->foreign('requestedByUserID', 'ssee_req_requester_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->string('status')->default('pending');

            $table->unsignedBigInteger('reviewedByUserID')->nullable();
            $table->foreign('reviewedByUserID', 'ssee_req_reviewer_fk')
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
        Schema::dropIfExists('service_subscription_extension_requests');
    }
};
