<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'brandID')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropForeign(['brandID']);
                $table->dropColumn('brandID');
            });
        }

        Schema::dropIfExists('brands');
    }

    public function down(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('storeID')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('storeID')
                ->references('id')
                ->on('stores')
                ->cascadeOnDelete();

            $table->unique(['storeID', 'slug']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('brandID')->nullable()->after('storeID');

            $table->foreign('brandID')
                ->references('id')
                ->on('brands')
                ->nullOnDelete();
        });
    }
};
