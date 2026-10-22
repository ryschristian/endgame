<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $users = [
            [
                'name' => 'Test User',
                'email' => 'user@example.test',
            ],
            [
                'name' => 'Second User',
                'email' => 'user2@example.test',
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'is_system_admin' => false,
                    'auth_version' => 1,
                ]
            );
        }
    }
}
