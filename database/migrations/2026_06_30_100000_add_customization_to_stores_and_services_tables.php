<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->json('customization')->nullable()->after('logo');
            $table->json('customizationData')->nullable()->after('customization');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->json('customization')->nullable()->after('logo');
            $table->json('customizationData')->nullable()->after('customization');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['customization', 'customizationData']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['customization', 'customizationData']);
        });
    }
};
