<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        //create admin user
        $this->call(AdminUserSeeder::class);
        //create services
        $this->call(ServiceSeeder::class);
        //create schedule
        $this->call(ScheduleSeeder::class);
        //create tools
        $this->call(ToolSeeder::class);
        //create service with tools
        $this->call(ServiceToolSeeder::class);
    }
}
