<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'view admin analytics', 'guard_name' => 'web']);

        Role::findByName('Admin', 'web')?->givePermissionTo('view admin analytics');
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Role::findByName('Admin', 'web')?->revokePermissionTo('view admin analytics');

        Permission::where('name', 'view admin analytics')
            ->where('guard_name', 'web')
            ->delete();
    }
};
