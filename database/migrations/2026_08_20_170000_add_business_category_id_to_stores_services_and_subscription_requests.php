<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->unsignedBigInteger('businessCategoryID')->nullable()->after('areaID');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->unsignedBigInteger('businessCategoryID')->nullable()->after('areaID');
        });

        Schema::table('store_subscription_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('businessCategoryID')->nullable()->after('storeName');
        });

        Schema::table('service_subscription_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('businessCategoryID')->nullable()->after('serviceName');
        });

        foreach (DB::table('stores')->select(['id', 'areaID'])->get() as $store) {
            $categoryId = DB::table('areas')->where('id', $store->areaID)->value('categoryID');
            if ($categoryId !== null) {
                DB::table('stores')->where('id', $store->id)->update(['businessCategoryID' => $categoryId]);
            }
        }

        foreach (DB::table('services')->select(['id', 'areaID'])->get() as $service) {
            $categoryId = DB::table('areas')->where('id', $service->areaID)->value('categoryID');
            if ($categoryId !== null) {
                DB::table('services')->where('id', $service->id)->update(['businessCategoryID' => $categoryId]);
            }
        }

        Schema::table('stores', function (Blueprint $table) {
            $table->foreign('businessCategoryID')
                ->references('id')
                ->on('business_categories')
                ->restrictOnDelete();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->foreign('businessCategoryID')
                ->references('id')
                ->on('business_categories')
                ->restrictOnDelete();
        });

        Schema::table('store_subscription_requests', function (Blueprint $table) {
            $table->foreign('businessCategoryID')
                ->references('id')
                ->on('business_categories')
                ->restrictOnDelete();
        });

        Schema::table('service_subscription_requests', function (Blueprint $table) {
            $table->foreign('businessCategoryID')
                ->references('id')
                ->on('business_categories')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_subscription_requests', function (Blueprint $table) {
            $table->dropForeign(['businessCategoryID']);
            $table->dropColumn('businessCategoryID');
        });

        Schema::table('store_subscription_requests', function (Blueprint $table) {
            $table->dropForeign(['businessCategoryID']);
            $table->dropColumn('businessCategoryID');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['businessCategoryID']);
            $table->dropColumn('businessCategoryID');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropForeign(['businessCategoryID']);
            $table->dropColumn('businessCategoryID');
        });
    }
};
