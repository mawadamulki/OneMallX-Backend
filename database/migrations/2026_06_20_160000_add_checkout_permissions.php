<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'place orders', 'guard_name' => 'web']);

        Role::findByName('Customer', 'web')?->givePermissionTo('place orders');
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Role::findByName('Customer', 'web')?->revokePermissionTo('place orders');

        Permission::where('name', 'place orders')
            ->where('guard_name', 'web')
            ->delete();
    }
};
