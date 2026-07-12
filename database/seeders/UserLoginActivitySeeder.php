<?php

namespace Database\Seeders;

use App\Models\UserLoginActivity;
use Illuminate\Database\Seeder;

class UserLoginActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        UserLoginActivity::factory()->count(10)->create();
    }
}
