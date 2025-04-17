<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\NotificationTrait;
use App\Models\Guest;
use App\Models\Guest_Spot_Log;
use App\Models\License_Plate;
use App\Models\Mqtt_Spot_Log;
use App\Models\Public_Spot;
use App\Models\Public_Spot_Log;
use App\Models\Reservable_Spot_Log;
use App\Models\Spot_Management;
use App\Services\MqttService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class SpotLogController extends Controller
{
    use NotificationTrait;

    protected $mqttService;
    protected $entered_at;
    protected $exitTime;

    public function __construct()
    {
        $this->mqttService = new MqttService();
    }

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
        return DB::transaction(function () use ($request) {
            $userPlate = License_Plate::where('plate', $request->plate)->first();
            $this->entered_at = Carbon::now();

            if ($userPlate) {
                return $this->handleUserParking($userPlate);
            }

            return $this->handleGuestParking($request->plate);
        });
    }

    private function handleUserParking($userPlate)
    {
        return DB::transaction(function () use ($userPlate) {
            $user = $userPlate->user;
            $activeReservation = $user->activeReservation;

            if ($activeReservation && $user->activeReservationWithinLimit) {
                return $this->parkInReservedSpot($activeReservation, $userPlate->plate);
            } else if ($activeReservation) {
                return response('User have active reservation but arrived earlier than expected, you can enter garage 15 minutes earlier the reservation or you are not authorized');
            } else {
                return $this->parkInPublicSpot($userPlate->plate);
            }

        });
    }

    private function checkOrCreateLog($modelClass, $plate, $location, $enteredAt, $reservable_spot_id = null)
    {
        $log = $modelClass::where([
            ['license_plate', '=', $plate],
            ['is_payed', '=', 0],
        ])->whereDate('entered_at', Carbon::today())->first();

        if (!$log) {
            $data = [
                'license_plate' => $plate,
                'entered_at' => $enteredAt,
            ];
            $reservable_spot_id ? $data['reservable_spot_id'] = $reservable_spot_id : $reservable_spot_id = null;

            $modelClass::create($data);
            // Log and publish
            $this->logAndPublish($plate, $location, true);
        } else {
            $this->logAndPublish($plate, $location);
        }
        $this->mqttService->publish('gate/entry', 'open');
    }

    private function parkInReservedSpot($reservation, $plate)
    {
        return DB::transaction(function () use ($reservation, $plate) {
            $this->checkOrCreateLog(Reservable_Spot_Log::class, $plate, 'Reservable Spot', $this->entered_at, $reservation->reservable_spot_id);
            return response('Success: User reserved car parked');
        });
    }

    private function checkPublicSpotAvailability()
    {
        $allSpots = Public_Spot::count();
        $usedSpots = Mqtt_Spot_Log::LocationCount('Public Spot');
        return $allSpots == $usedSpots;
    }

    private function parkInPublicSpot($plate)
    {
        return DB::transaction(function () use ($plate) {
            if ($this->checkPublicSpotAvailability()) {
                return response('Not enough spots');
            }
            $this->checkOrCreateLog(Public_Spot_Log::class, $plate, 'Public Spot', $this->entered_at);
            return response('Success: User car parked');
        });
    }

    private function handleGuestParking($plate)
    {
        return DB::transaction(function () use ($plate) {

            if ($this->checkPublicSpotAvailability()) {
                return response('Not enough spots');
            }

            $guestPlate = Guest::where('license_plate', $plate)->first();

            if (!$guestPlate) {
                return $this->registerNewGuest($plate);
            }

            return $this->processGuestParking($guestPlate);
        });
    }

    private function registerNewGuest($plate)
    {
        return DB::transaction(function () use ($plate) {
            $newGuest = Guest::create(['license_plate' => $plate, 'counter' => 1]);

            return $this->logGuestParking($newGuest);
        });
    }

    private function processGuestParking($guestPlate)
    {
        return DB::transaction(function () use ($guestPlate) {
            if (Mqtt_Spot_Log::where('license_plate', $guestPlate->license_plate)->exists()) {
                $this->mqttService->publish('gate/entry', 'open');
                return response('Guest Already In Garage');
            }

            if ($guestPlate->counter >= 3) {
                return response('Failed: Guest parking limit reached');
            }

            $guestPlate->increment('counter');
            return $this->logGuestParking($guestPlate);
        });
    }

    private function logGuestParking($guestPlate)
    {
        return DB::transaction(function () use ($guestPlate) {
            $this->checkOrCreateLog(Guest_Spot_Log::class, $guestPlate->license_plate, 'Public Spot', $this->entered_at);
            return response('Success: Guest car parked');
        });
    }

    private function logAndPublish($licensePlate, $location, $log = false)
    {
        if ($log) {
            // Insert into mqtt_spot_log
            Mqtt_Spot_Log::create([
                'license_plate' => $licensePlate,
                'location' => $location,
                'entered_at' => $this->entered_at
            ]);
        }

        $count = Mqtt_Spot_Log::LocationCount($location);

        // Publish to MQTT
        $this->mqttService->publish('spot/log', $count);
    }

    /**
     * NOW HANDLE EXIT LOGIC
     */

    public function exitParking(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $this->exitTime = Carbon::now();
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
                $currentLog = $logModel::where([
                    ['license_plate', $plate],
                    ['is_payed', 1],
                ])->orderBy('entered_at', 'desc')->first();
                if ($currentLog) {
                    $this->mqttService->publish('gate/exit', 'open');
                    return response('User already paid for exit');
                }
                return response('User has no entry logs');
            }

            $parkingTime = round($currentLog->entered_at->floatDiffInHours($this->exitTime), 2);
            $parkingPricePerHour = Spot_Management::where('type', $spotType ?: 'reservable')->first()->price_per_hour;
            $invoice = $parkingTime * $parkingPricePerHour;
            $data = [
                'invoice_price' => $invoice,
                'exited_at' => $this->exitTime
            ];
            if ($user && $deductBalance) {
                $balance = $user->userData->balance;
                $status = null;
                $balance < $invoice ? $this->sendSms("You don't have enough balance to pay for spot. Your invoice is $invoice and your current balance is $balance.", $user->userData->phone) : $status = true;
                if ($status == null)
                    return response('Insufficient balance.' . $invoice);
                $user->userData()->decrement('balance', $invoice);
                $user->activeReservation()->update(['is_active' => 0]);
                $data['is_payed'] = 1;
                $currentLog->update($data);
                Mqtt_Spot_Log::where('license_plate', $plate)->delete();
                $this->mqttService->publish('gate/exit', 'open');
            } else {
                $guestPayment = (new PaymentController())->guestPayment($invoice, $currentLog->license_plate);
                if ($guestPayment) {
                    $qrPath = QrCode::format('png')
                        ->size(450)
                        ->margin(2)
                        ->errorCorrection('H')
                        ->generate($guestPayment);

                    $s3Path = 'qrcodes/' . uniqid() . '.png';
                    Storage::disk('s3')->put($s3Path, $qrPath);
                    $paymentUrl = Storage::disk('s3')->url($s3Path);
                    $this->mqttService->publish('guest/payment', $paymentUrl);
                    $data['qr_payment'] = $paymentUrl;
                    $currentLog->update($data);
                    return response('Guest payment qrcode generated successfully. ' . $paymentUrl);
                }
            }
            return response("{$invoice} " . ($user ? ($spotType ? 'PUBLIC' : 'USER RESERVED') : 'GUEST PUBLIC') . " {$parkingTime}");
        });
    }
}
