<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-aggregated daily metrics for fast analytics at scale.
 * Populated by a future scheduled job (not wired yet); dashboards still use live queries today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('storeID');
            $table->date('date');
            $table->unsignedInteger('revenue')->default(0);
            $table->unsignedInteger('orders_count')->default(0);
            $table->unsignedInteger('customers_count')->default(0);
            $table->timestamps();

            $table->foreign('storeID')
                ->references('id')
                ->on('stores')
                ->cascadeOnDelete();

            $table->unique(['storeID', 'date'], 'store_daily_stats_store_date_unique');
            $table->index('date', 'store_daily_stats_date_index');
        });

        Schema::create('service_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('serviceID');
            $table->date('date');
            $table->unsignedInteger('revenue')->default(0);
            $table->unsignedInteger('bookings_count')->default(0);
            $table->unsignedInteger('cancelled_bookings_count')->default(0);
            $table->unsignedInteger('customers_count')->default(0);
            $table->timestamps();

            $table->foreign('serviceID')
                ->references('id')
                ->on('services')
                ->cascadeOnDelete();

            $table->unique(['serviceID', 'date'], 'service_daily_stats_service_date_unique');
            $table->index('date', 'service_daily_stats_date_index');
        });

        Schema::create('platform_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->unsignedInteger('user_registrations')->default(0);
            $table->unsignedInteger('orders_count')->default(0);
            $table->unsignedInteger('bookings_count')->default(0);
            $table->unsignedInteger('platform_revenue')->default(0);
            $table->unsignedInteger('pending_reports')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_daily_stats');
        Schema::dropIfExists('service_daily_stats');
        Schema::dropIfExists('store_daily_stats');
    }
};
