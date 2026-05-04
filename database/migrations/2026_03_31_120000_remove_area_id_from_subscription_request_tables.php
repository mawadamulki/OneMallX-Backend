<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_subscription_requests', function (Blueprint $table) {
            $table->dropForeign(['areaID']);
            $table->dropColumn('areaID');
        });

        Schema::table('service_subscription_requests', function (Blueprint $table) {
            $table->dropForeign(['areaID']);
            $table->dropColumn('areaID');
        });
    }

    public function down(): void
    {
        Schema::table('store_subscription_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('areaID')->after('storeName');
            $table->foreign('areaID')
                ->references('id')
                ->on('areas')
                ->cascadeOnDelete();
        });

        Schema::table('service_subscription_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('areaID')->after('price');
            $table->foreign('areaID')
                ->references('id')
                ->on('areas')
                ->cascadeOnDelete();
        });
    }
};
