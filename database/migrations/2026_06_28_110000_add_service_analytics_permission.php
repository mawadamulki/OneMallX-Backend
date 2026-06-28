<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'view service analytics', 'guard_name' => 'web']);

        Role::findByName('Service Provider', 'web')?->givePermissionTo('view service analytics');
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Role::findByName('Service Provider', 'web')?->revokePermissionTo('view service analytics');

        Permission::where('name', 'view service analytics')
            ->where('guard_name', 'web')
            ->delete();
    }
};
