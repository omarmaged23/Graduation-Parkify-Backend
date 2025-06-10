<?php

namespace Database\Seeders;

use App\Models\Reservation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReservationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Reservation::create([
            'license_plate' => 'ق ص س 4 3 1',
            'expected_arrival' => '2025-06-10 12:10:30',
            'user_id' => 1,
            'reservable_spot_id' => 1,
        ]);
    }
}
