<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::updateOrCreate(['email' => 'ivan@gmail.com'], [
            'name' => 'Ivan',
            'password' => 'password123',
        ]);

        User::updateOrCreate(['email' => 'alex@example.com'], [
            'name' => 'Alex Morgan',
            'password' => 'password123',
        ]);

        User::updateOrCreate(['email' => 'jamie@example.com'], ['name' => 'Jamie Chen', 'password' => 'password123']);
        User::updateOrCreate(['email' => 'priya@example.com'], ['name' => 'Priya Shah', 'password' => 'password123']);
    }
}
