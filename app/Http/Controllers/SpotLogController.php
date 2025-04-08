<?php

namespace App\Http\Controllers;

use App\Jobs\PublishMqttUpdate;
use App\Models\Guest;
use App\Models\Guest_Spot_Log;
use App\Models\License_Plate;
use App\Models\Public_Spot_Log;
use App\Models\Reservable_Spot_Log;
use App\Models\Spot_Management;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpotLogController extends Controller
{
    /** Needed Logic
         * 1) Check whether the car belongs to a guest or registered user
         *
         *                              User Scenario
         * 2) If user, check for active reservation that belongs to this car
         * 3) If no available reservations, check for public spots availability
         * 4) If not available just don't allow anyone to enter otherwise complete conditions
         * 5) Add necessary user log data and open gate
         *
         *                              Guest Scenario
         * 6) If it's a guest check the car limit (if exists) and do the necessary operations
     */

    public function parkCar(Request $request)
    {
        $plate = $request->plate;
        $userPlate = License_Plate::where('plate', $plate)->first();

        if ($userPlate) {
            return $this->handleUserParking($userPlate);
        }

        return $this->handleGuestParking($plate);
    }

    private function handleUserParking($userPlate)
    {
        $user = $userPlate->user;
        $activeReservation = $user->activeReservation;

        if ($activeReservation) {
            return $this->parkInReservedSpot($activeReservation, $userPlate->plate);
        }

        return $this->parkInPublicSpot($userPlate->plate);
    }

    private function parkInReservedSpot($reservation, $plate)
    {
        $log = Reservable_Spot_Log::create([
            'license_plate' => $plate,
            'entered_at' => now(),
            'reservable_spot_id' => $reservation->reservable_spot_id
        ]);

        // ✅ Dispatch MQTT update asynchronously
        PublishMqttUpdate::dispatch($plate, 'reserved', now());

        return response('Success: User reserved car parked');
    }

    private function parkInPublicSpot($plate)
    {
        $log = Public_Spot_Log::create([
            'license_plate' => $plate,
            'entered_at' => now()
        ]);

        // ✅ Dispatch MQTT update asynchronously
        PublishMqttUpdate::dispatch($plate, 'public', now());

        return response('Success: User car parked');
    }

    private function handleGuestParking($plate)
    {
        $guestPlate = Guest::where('license_plate', $plate)->first();

        if (!$guestPlate) {
            return $this->registerNewGuest($plate);
        }

        return $this->processGuestParking($guestPlate);
    }

    private function registerNewGuest($plate)
    {
        $newGuest = Guest::create(['license_plate' => $plate, 'counter' => 1]);
        return $this->logGuestParking($newGuest);
    }

    private function processGuestParking($guestPlate)
    {
        if ($guestPlate->counter >= 3) {
            return response('Failed: Guest parking limit reached');
        }

        $guestPlate->increment('counter');
        return $this->logGuestParking($guestPlate);
    }

    private function logGuestParking($guestPlate)
    {
        $log = Guest_Spot_Log::create([
            'guest_plate' => $guestPlate->license_plate,
            'entered_at' => now()
        ]);

        // ✅ Dispatch MQTT update asynchronously
        PublishMqttUpdate::dispatch($guestPlate->license_plate, 'guest', now());

        return response('Success: Guest car parked');
    }

    /**
     * NOW HANDLE EXIT LOGIC
     */

    public function exitParking(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $userPlate = License_Plate::where('plate', $request->plate)->first();
            if ($userPlate) {
                return $this->handleUserExit($userPlate);
            }
            return $this->handleGuestExit($request->plate);
        });
    }

    private function handleUserExit($userPlate)
    {
        return DB::transaction(function () use ($userPlate) {
            $user = $userPlate->user;
            $activeReservation = $user->activeReservation;

            if ($activeReservation) {
                return $this->processExit($user, Reservable_Spot_Log::class, $userPlate->plate);
            }
            return $this->processExit($user, Public_Spot_Log::class, $userPlate->plate, 'public');
        });
    }

    private function handleGuestExit($plate)
    {
        return DB::transaction(function () use ($plate) {
            $guestPlate = Guest::where('license_plate', $plate)->first();
            if (!$guestPlate) {
                return response('Guest has no entry logs or plate is misread');
            }
            return $this->processExit(null, Guest_Spot_Log::class, $plate, 'public', false);
        });
    }

    /**
     * IN CASE OF GUEST CREATE QR CODE FOR PAYMENT WITH INVOICE AND GUEST LICENSE PLATE.
     * AFTER PAYING YOUR FEES ONLY THEN YOU UPDATE GUEST LOG TABLE AND EXIT THE GARAGE.
     */
    private function processExit($user, $logModel, $plate, $spotType = null, $deductBalance = true)
    {
        return DB::transaction(function () use ($user, $logModel, $plate, $spotType, $deductBalance) {
            $currentLog = $logModel::where([
                ['license_plate', $plate],
                ['is_payed', 0],
            ])->orderBy('entered_at', 'desc')->first();

            if (!$currentLog) {
                return response('User has no entry logs');
            }

            $exitTime = now();
            $parkingTime = round($currentLog->entered_at->floatDiffInHours($exitTime), 2);
            $parkingPricePerHour = Spot_Management::where('type', $spotType ?: 'reservable')->first()->price_per_hour;
            $invoice = $parkingTime * $parkingPricePerHour;

            if ($user && $deductBalance) {
                $user->userData()->decrement('balance', $invoice);
                $user->activeReservation()->update(['is_active' => 0]);
            }

            $currentLog->update([
                'invoice_price' => $invoice,
                'exited_at' => $exitTime,
                'is_payed' => 1
            ]);

            return response("{$invoice} ".($user ? ($spotType ? 'PUBLIC' : 'USER RESERVED') : 'GUEST PUBLIC')." {$parkingTime}");
        });
    }
}
