<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['created_at', 'status'], 'orders_created_at_status_index');
            $table->index(['status', 'created_at'], 'orders_status_created_at_index');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index(['storeID', 'lineType'], 'order_items_store_line_type_index');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['serviceID', 'date', 'status'], 'bookings_service_date_status_index');
            $table->index(['serviceID', 'created_at', 'status'], 'bookings_service_created_status_index');
            $table->index(['serviceItemID', 'date'], 'bookings_service_item_date_index');
            $table->index(['employeeID', 'date'], 'bookings_employee_date_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('created_at', 'users_created_at_index');
            $table->index('status', 'users_status_index');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->index('accountStatus', 'stores_account_status_index');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->index('accountStatus', 'services_account_status_index');
        });

        Schema::table('store_subscription_payments', function (Blueprint $table) {
            $table->index('created_at', 'store_subscription_payments_created_at_index');
        });

        Schema::table('service_subscription_payments', function (Blueprint $table) {
            $table->index('created_at', 'service_subscription_payments_created_at_index');
        });

        Schema::table('rate_reports', function (Blueprint $table) {
            $table->index('created_at', 'rate_reports_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_created_at_status_index');
            $table->dropIndex('orders_status_created_at_index');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_store_line_type_index');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_service_date_status_index');
            $table->dropIndex('bookings_service_created_status_index');
            $table->dropIndex('bookings_service_item_date_index');
            $table->dropIndex('bookings_employee_date_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_created_at_index');
            $table->dropIndex('users_status_index');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex('stores_account_status_index');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex('services_account_status_index');
        });

        Schema::table('store_subscription_payments', function (Blueprint $table) {
            $table->dropIndex('store_subscription_payments_created_at_index');
        });

        Schema::table('service_subscription_payments', function (Blueprint $table) {
            $table->dropIndex('service_subscription_payments_created_at_index');
        });

        Schema::table('rate_reports', function (Blueprint $table) {
            $table->dropIndex('rate_reports_created_at_index');
        });
    }
};
