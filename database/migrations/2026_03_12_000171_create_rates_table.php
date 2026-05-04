<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('userID');
            $table->string('rateableType');
            $table->unsignedBigInteger('rateableID');
            $table->unsignedTinyInteger('score');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('userID')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->index(['rateableType', 'rateableID']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rates');
    }
};

