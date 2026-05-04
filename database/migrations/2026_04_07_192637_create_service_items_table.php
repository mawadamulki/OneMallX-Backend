<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('service_items', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('serviceID');
        $table->string('name');
        $table->integer('price');
        $table->integer('duration'); // بالدقائق
        $table->timestamps();
        $table->softDeletes();

        $table->foreign('serviceID')
            ->references('id')
            ->on('services')
            ->cascadeOnDelete();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_items');
    }
};
