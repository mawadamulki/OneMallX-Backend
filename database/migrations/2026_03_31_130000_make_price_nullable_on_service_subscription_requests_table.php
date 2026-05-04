<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_subscription_requests', function (Blueprint $table) {
            $table->integer('price')->nullable()->change();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->integer('price')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('service_subscription_requests')->whereNull('price')->update(['price' => 0]);
        DB::table('services')->whereNull('price')->update(['price' => 0]);

        Schema::table('service_subscription_requests', function (Blueprint $table) {
            $table->integer('price')->nullable(false)->change();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->integer('price')->nullable(false)->change();
        });
    }
};
