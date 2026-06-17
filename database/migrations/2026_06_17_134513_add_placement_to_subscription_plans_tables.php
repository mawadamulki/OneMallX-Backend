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
        Schema::table('store_subscription_plans', function (Blueprint $table) {
            $table->enum('adsPlacement', ['home', 'deals'])->default('deals')->after('adsDuration');
        });

        Schema::table('service_subscription_plans', function (Blueprint $table) {
            $table->enum('adsPlacement', ['home', 'deals'])->default('deals')->after('adsDuration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_subscription_plans', function (Blueprint $table) {
            $table->dropColumn('adsPlacement');
        });

        Schema::table('service_subscription_plans', function (Blueprint $table) {
            $table->dropColumn('adsPlacement');
        });
    }
};
