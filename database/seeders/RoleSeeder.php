<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::create(['name' => 'Customer', 'guard_name' => 'web']);
        Role::create(['name' => 'Store Owner', 'guard_name' => 'web']);
        Role::create(['name' => 'Service Provider', 'guard_name' => 'web']);
        Role::create(['name' => 'Mall Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);

        $mallAdmin = Role::findByName('Mall Admin', 'web');
        $mallAdmin->givePermissionTo([]);

        $Admin = Role::findByName('Admin', 'web');
        $Admin->givePermissionTo([
            'manage subscription plans',
            'get subscription plans',
            'manage floors',
            'manage areas',
            'manage store subscription requests',
            'manage service subscription requests',
            'show floors and areas',
            'manage stores',
            'manage services',
            'manage users',
        ]);

        $serviceProvider = Role::findByName('Service Provider', 'web');
        $serviceProvider->givePermissionTo([
            'get subscription plans',
            'manage service subscriptions',
        ]);

        $storeOwner = Role::findByName('Store Owner', 'web');
        $storeOwner->givePermissionTo([
            'get subscription plans',
            'manage store subscriptions',
        ]);

        $customer = Role::findByName('Customer', 'web');
        $customer->givePermissionTo([
            'show floors and areas',
        ]);
    }
}
