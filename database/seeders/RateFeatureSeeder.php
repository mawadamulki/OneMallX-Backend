<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RateFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        Role::findByName('Customer', 'web')->givePermissionTo([
            'rate entities',
            'report rates',
        ]);

        Role::findByName('Store Owner', 'web')->givePermissionTo([
            'view store ratings',
            'report rates',
        ]);

        Role::findByName('Service Provider', 'web')->givePermissionTo([
            'view service ratings',
            'report rates',
        ]);

        Role::findByName('Admin', 'web')->givePermissionTo([
            'manage rates',
        ]);
    }
}
