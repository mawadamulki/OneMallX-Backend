<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_subscription_plans', function (Blueprint $table) {
            $table->boolean('accepting_subscriptions')->default(true);
        });

        Schema::table('service_subscription_plans', function (Blueprint $table) {
            $table->boolean('accepting_subscriptions')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('store_subscription_plans', function (Blueprint $table) {
            $table->dropColumn('accepting_subscriptions');
        });

        Schema::table('service_subscription_plans', function (Blueprint $table) {
            $table->dropColumn('accepting_subscriptions');
        });
    }
};
