<?php

namespace Database\Seeders;

use App\Models\Guest_Spot_Log;
use App\Models\Public_Spot_Log;
use App\Models\Public_Spot_Used;
use App\Models\Reservable_Spot_Log;
use App\Models\User;
use App\Models\User_Gift;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DashboardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $publicSpots = [
            1 => ['P1x1','P2x2','banha'],
            2 => ['PO1x1','PO2x2','obour'],
            3 => ['PS1x1','PS2x2','sohag'],
            4 => ['PK1x1','PK2x2','elshorouk'],
            5 => ['PD1x1','PD2x2','disneyland'],
        ];
        foreach (range(1, 50) as $i) {
            Guest_Spot_Log::create([
                'license_plate' => 'أ ب 44',
                'location_id' => rand(1, 5),
                'is_payed' => 1,
                'invoice_price' => rand(100, 500),
                'entered_at' => Carbon::now()->subMonths(rand(0, 11))->startOfMonth()->addDays(rand(0, 27))
            ]);
            $userID = rand(1,2);
            $userPlate = [
                1 => 'ق ص س 4 3 1',
                2 => 'ي ي ي 1 1 1'
            ];
            $locID = rand(1,5);
            $locSpot = rand(0,1);
            Public_Spot_Log::create([
                'license_plate' => $userPlate[$userID],
                'user_id' => $userID,
                'location_id' => $locID,
                'is_payed' => 1,
                'invoice_price' => rand(50, 500),
                'entered_at' => Carbon::now()->subMonths(rand(0, 11))->startOfMonth()->addDays(rand(0, 27))
            ]);
            Public_Spot_Used::create([
                'spot_code' => $publicSpots[$locID][$locSpot],
                'location_id' => $locID,
            ]);
            Reservable_Spot_Log::create([
                'license_plate' => $userPlate[$userID],
                'user_id' => $userID,
                'is_payed' => 1,
                'invoice_price' => rand(100, 500),
                'reservable_spot_id' => rand(1, 4),
                'entered_at' => Carbon::now()->subMonths(rand(0, 11))->startOfMonth()->addDays(rand(0, 27))
            ]);

            $user = User::create([
                'name' => "user$i",
                'email' => "user$i@mail.ru",
                'password' => Hash::make('password')
            ]);
            $user->userData()->create([
                'national' => rand(10000000000000,99999999999999),
                'phone' => "0".rand(1000000000,9999999999),
                'is_active' => rand(0,1),
                'balance' => '100000',
                'points' => '100000',
            ]);

            User_Gift::create([
                'applied_to_payment' => 1,
                'is_active' => 0,
                'user_id' => rand(1,$user->id),
                'gift_id' => rand(1,10),
            ]);

        }
    }
}
