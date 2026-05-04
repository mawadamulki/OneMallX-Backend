<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->enum('accountStatus', ['active', 'notActive'])->default('notActive')->after('status');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->enum('accountStatus', ['active', 'notActive'])->default('notActive')->after('status');
        });

        DB::table('stores')->where('status', 'active')->update(['accountStatus' => 'active']);
        DB::table('services')->where('status', 'active')->update(['accountStatus' => 'active']);
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('accountStatus');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('accountStatus');
        });
    }
};
