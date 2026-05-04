<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Mall;
use App\Models\User;


class MallSeeder extends Seeder
{
    
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@gmail.com')->first();

         Mall::create([
            'name' => 'Default Mall',
            'country' => 'Syria',
            'mallOwnerID' => 1
        ]);
    }
}
