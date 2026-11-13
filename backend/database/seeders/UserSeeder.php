<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@freightpath.test'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('Admin12345'),
                'role_id' => Role::where('name', 'admin')->value('id'),
            ],
        );
    }
}
