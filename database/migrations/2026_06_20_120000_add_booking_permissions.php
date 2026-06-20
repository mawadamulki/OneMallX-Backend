<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'book services', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view service bookings', 'guard_name' => 'web']);

        Role::findByName('Customer', 'web')?->givePermissionTo('book services');
        Role::findByName('Service Provider', 'web')?->givePermissionTo('view service bookings');
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Role::findByName('Customer', 'web')?->revokePermissionTo('book services');
        Role::findByName('Service Provider', 'web')?->revokePermissionTo('view service bookings');

        Permission::whereIn('name', ['book services', 'view service bookings'])
            ->where('guard_name', 'web')
            ->delete();
    }
};
