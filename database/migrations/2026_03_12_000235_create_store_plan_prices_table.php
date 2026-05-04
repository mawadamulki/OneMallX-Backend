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
        Schema::create('store_plan_prices', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('storeSubscriptionPlanID');
            $table->foreign('storeSubscriptionPlanID')
                ->references('id')
                ->on('store_subscription_plans')
                ->cascadeOnDelete();

            $table->integer('duration'); // months
            $table->integer('price');

            $table->unique(['storeSubscriptionPlanID', 'duration'], 'plan_price_unique');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_plan_prices');
    }
};
