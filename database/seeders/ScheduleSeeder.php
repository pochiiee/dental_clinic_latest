<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('schedules')->insert([
            [
                'schedule_id' => 1,
                'start_time' => '10:00:00',
                'end_time' => '12:00:00',
                'max_capacity' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'schedule_id' => 2, 
                'start_time' => '13:00:00',
                'end_time' => '15:00:00',
                'max_capacity' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'schedule_id' => 3,
                'start_time' => '15:00:00', 
                'end_time' => '17:00:00',
                'max_capacity' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}