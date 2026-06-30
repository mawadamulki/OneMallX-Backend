<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->json('detailCustomization')->nullable()->after('customizationData');
            $table->json('detailCustomizationData')->nullable()->after('detailCustomization');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->json('detailCustomization')->nullable()->after('customizationData');
            $table->json('detailCustomizationData')->nullable()->after('detailCustomization');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['detailCustomization', 'detailCustomizationData']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['detailCustomization', 'detailCustomizationData']);
        });
    }
};
