<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rates', function (Blueprint $table) {
            $table->unique(['userID', 'rateableType', 'rateableID'], 'rates_user_rateable_unique');
        });
    }

    public function down(): void
    {
        Schema::table('rates', function (Blueprint $table) {
            $table->dropUnique('rates_user_rateable_unique');
        });
    }
};
