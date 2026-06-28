<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'view store analytics', 'guard_name' => 'web']);

        Role::findByName('Store Owner', 'web')?->givePermissionTo('view store analytics');
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Role::findByName('Store Owner', 'web')?->revokePermissionTo('view store analytics');

        Permission::where('name', 'view store analytics')
            ->where('guard_name', 'web')
            ->delete();
    }
};
