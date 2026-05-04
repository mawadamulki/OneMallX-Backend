<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('number');
            $table->unsignedBigInteger('floorID');
            $table->foreign('floorID')
                ->references('id')
                ->on('floors')
                ->cascadeOnDelete();

            $table->nullableMorphs('planable');

            $table->string('usageType');
            $table->string('category');
            $table->integer('maxCapacity');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
