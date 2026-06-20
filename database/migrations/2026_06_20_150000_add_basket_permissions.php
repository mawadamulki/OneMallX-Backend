<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'manage basket', 'guard_name' => 'web']);

        Role::findByName('Customer', 'web')?->givePermissionTo('manage basket');
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Role::findByName('Customer', 'web')?->revokePermissionTo('manage basket');

        Permission::where('name', 'manage basket')
            ->where('guard_name', 'web')
            ->delete();
    }
};
