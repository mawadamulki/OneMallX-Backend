<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('userID');
            $table->string('title');
            $table->text('body');
            $table->string('type', 50);
            $table->json('data')->nullable();
            $table->timestamp('readAt')->nullable();
            $table->timestamps();

            $table->foreign('userID')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->index(['userID', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
