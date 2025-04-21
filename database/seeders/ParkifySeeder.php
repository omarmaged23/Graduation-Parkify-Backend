<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Gift;
use App\Models\Location;
use App\Models\Public_Spot;
use App\Models\Refund;
use App\Models\Reservable_Spot;
use App\Models\Spot_Management;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ParkifySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // generate 4 users
        $user1 = User::create([
            'name' =>'omar maged',
            'email' => 'omar@gmail.com',
            'password' => Hash::make('password'),
        ]);
        $user2 = User::create([
            'name' =>'omar khaled',
            'email' => 'omarkhaled@gmail.com',
            'password' => Hash::make('password'),
        ]);
        $user3 = User::create([
            'name' =>'mohsen',
            'email' => 'mohsen@gmail.com',
            'password' => Hash::make('password'),
        ]);
        $user4 = User::create([
            'name' =>'ali abdo',
            'email' => 'abdo@gmail.com',
            'password' => Hash::make('password'),
        ]);

        // generate their needed data
        $user1->userData()->create([
            'national' => '33333333333333',
            'phone' => '01141667608'
        ]);
        $user1->licensePlates()->create([
            'plate' => 'ق ص س 4 3 1'
        ]);

        $user2->userData()->create([
            'national' => '44444444444444',
            'phone' => '01555899576'
        ]);
        $user2->licensePlates()->create([
            'plate' => 'ي ي ي 1 1 1'
        ]);

        $user3->userData()->create([
            'national' => '55555555555555',
            'phone' => '01002319312'
        ]);
        $user3->licensePlates()->create([
            'plate' => 'م م ع 4 3 1'
        ]);

        $user4->userData()->create([
            'national' => '66666666666666',
            'phone' => '01007788459'
        ]);
        $user4->licensePlates()->create([
            'plate' => 'أ ي ي ي 4 3 1'
        ]);

        // generate locations
        Location::create([
            'name' => 'banha',
            'address' => 'somewhere in banha',
            'gps_location' => 'http://banhagps.com'
        ]);
        Location::create([
            'name' => 'obour',
            'address' => 'somewhere in obour',
            'gps_location' => 'http://obourgps.com'
        ]);

        // generate random gifts
        Gift::create([
            'description' => 'this is gift 1',
            'cost' => 40,
            'discount_percentage' => 10,
        ]);
        Gift::create([
            'description' => 'this is gift 2',
            'cost' => 80,
            'discount_percentage' => 20,
        ]);
        Gift::create([
            'description' => 'this is gift 3',
            'cost' => 120,
            'discount_percentage' => 30,
        ]);
        Gift::create([
            'description' => 'this is gift 4',
            'cost' => 160,
            'discount_percentage' => 40,
        ]);
        Gift::create([
            'description' => 'this is gift 5',
            'cost' => 200,
            'discount_percentage' => 50,
        ]);

        // generate refund
        Refund::create([
            'percentage' => 80
        ]);

        //generate spot management

        Spot_Management::create([
            'type' => 'public',
            'price_per_hour' => 40,
            'additional_guest_fees' => 10,
            'points_per_hour' => 20,
        ]);
        Spot_Management::create([
            'type' => 'reservable',
            'price_per_hour' => 50,
            'time_restriction' => 60,
            'reservation_fees' => 30,
            'points_per_hour' => 25,
        ]);

        // generate public spots
        Public_Spot::create([
            'spot_code' => 'P1x1',
            'management_id'=> 1,
            'location_id'=> 1,
        ]);
        Public_Spot::create([
            'spot_code' => 'P2x2',
            'management_id'=> 1,
            'location_id'=> 1,
        ]);
        Public_Spot::create([
            'spot_code' => 'P3x3',
            'management_id'=> 1,
            'location_id'=> 1,
        ]);
        Public_Spot::create([
            'spot_code' => 'P4x4',
            'management_id'=> 1,
            'location_id'=> 1,
        ]);

        // generate reservable spots
        Reservable_Spot::create([
            'spot_code' => 'R1x1',
            'management_id'=> 1,
            'location_id'=> 1,
        ]);
        Reservable_Spot::create([
            'spot_code' => 'R2x2',
            'management_id'=> 1,
            'location_id'=> 1,
        ]);
        Reservable_Spot::create([
            'spot_code' => 'R3x3',
            'management_id'=> 1,
            'location_id'=> 1,
        ]);
        Reservable_Spot::create([
            'spot_code' => 'R4x4',
            'management_id'=> 1,
            'location_id'=> 1,
        ]);

        // generate admin account
        Admin::create([
            'name' => 'omar maged',
            'email' => 'omar@yahoo.com',
            'password' => Hash::make('password'),
        ]);
        Admin::create([
            'name' => 'omar khaled',
            'email' => 'omarkhaled@yahoo.com',
            'password' => Hash::make('password'),
        ]);
    }
}
