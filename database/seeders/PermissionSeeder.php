<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
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
            'manage store subscriptions',
            'manage service subscriptions',
            'manage store products',
            'manage service catalog',
            'rate entities',
            'report rates',
            'view store ratings',
            'view service ratings',
            'manage rates',
            'book services',
            'view service bookings',
            'view store orders',
            'manage basket',
            'place orders',
        ];
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }
}
