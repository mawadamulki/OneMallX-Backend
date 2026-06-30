<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->fullText(['name', 'shortDetail', 'detail'], 'products_search_fulltext');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->fullText(['name', 'description'], 'stores_search_fulltext');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->fullText(['name', 'description'], 'services_search_fulltext');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropFullText('products_search_fulltext');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropFullText('stores_search_fulltext');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropFullText('services_search_fulltext');
        });
    }
};
