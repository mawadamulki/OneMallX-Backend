<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_plan_prices', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('serviceSubscriptionPlanID');
            $table->foreign('serviceSubscriptionPlanID')
                ->references('id')
                ->on('service_subscription_plans')
                ->cascadeOnDelete();

            $table->integer('duration'); // months
            $table->integer('price');

            $table->unique(['serviceSubscriptionPlanID', 'duration'], 'plan_price_unique');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_plan_prices');
    }
};
