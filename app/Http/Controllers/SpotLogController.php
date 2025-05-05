<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\NotificationTrait;
use App\Models\Guest;
use App\Models\Guest_Spot_Log;
use App\Models\License_Plate;
use App\Models\Mqtt_Spot_Log;
use App\Models\Public_Spot;
use App\Models\Public_Spot_Log;
use App\Models\Reservable_Spot;
use App\Models\Reservable_Spot_Log;
use App\Models\Spot_Management;
use App\Models\User_Gift;
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
                return $this->parkInReservedSpot($activeReservation, $userPlate->plate, $user->id);
            } else if ($activeReservation) {
                $this->mqttService->publish('garage/entry/display/message', 'User Arrived Earlier Than Expected');
                // return response()->json(['error'=>'User have active reservation but arrived earlier than expected, you can enter garage 15 minutes earlier the reservation or you are not authorized'],422);
                return response()->json(['status' => 'error' , 'message' => 'User have active reservation but arrived earlier than expected, you can enter garage 15 minutes earlier the reservation or you are not authorized']);
            } else {
                return $this->parkInPublicSpot($userPlate->plate, $user->id);
            }

        });
    }

    private function checkOrCreateLog($modelClass, $plate, $location, $enteredAt, $user_id ,$reservable_spot_id = null)
    {
        if(!$user_id){
            $condition = [
                ['license_plate', '=', $plate],
                ['is_payed', '=', 0]
            ];
            $data = [
                'license_plate' => $plate,
                'entered_at' => $enteredAt
            ];
        } else {
            $condition = [
                ['license_plate', '=', $plate],
                ['is_payed', '=', 0],
                ['user_id', '=', $user_id],
            ];
            $data = [
                'license_plate' => $plate,
                'entered_at' => $enteredAt,
                'user_id' => $user_id,
            ];
        }
        $log = $modelClass::where($condition)
        ->whereDate('entered_at', Carbon::today())->orderBy('entered_at', 'desc')->first();

        if (!$log) {
            $reservable_spot_id ? $data['reservable_spot_id'] = $reservable_spot_id : $reservable_spot_id = null;

            $modelClass::create($data);
            // Log and publish
            $this->logAndPublish($plate, $location, true);
        } else {
            $this->logAndPublish($plate, $location);
        }
        $this->mqttService->publish('garage/entry_gate', 'open');
        $this->mqttService->publish('garage/entry/display/message', 'Welcome to parkify garage :D');
    }

    private function parkInReservedSpot($reservation, $plate,$user_id)
    {
        return DB::transaction(function () use ($reservation, $plate,$user_id) {
            $this->checkOrCreateLog(Reservable_Spot_Log::class, $plate, 'Reservable Spot', $this->entered_at,$user_id ,$reservation->reservable_spot_id);
            // return response()->json(['success' => 'User reserved car parked']);
            return response()->json(['status' => 'success','message'=> 'Welcome to parkify garage :D']);
        });
    }

    private function checkPublicSpotAvailability()
    {
        $allSpots = Public_Spot::count();
        $usedSpots = Mqtt_Spot_Log::LocationCount('Public Spot');
        return $allSpots == $usedSpots;
    }

    private function parkInPublicSpot($plate,$user_id)
    {
        return DB::transaction(function () use ($plate,$user_id) {
            if ($this->checkPublicSpotAvailability()) {
                $this->mqttService->publish('garage/entry_gate', 'full');
                $this->mqttService->publish('garage/entry/display/message', "Garage is full.\nVisit us later.");
                return response()->json(['error'=>'Not enough spots'],422);
            }
            $this->checkOrCreateLog(Public_Spot_Log::class, $plate, 'Public Spot', $this->entered_at,$user_id);

            // return response()->json(['success' => 'User car parked']);
            return response()->json(['status' => 'success','message'=> 'Welcome to parkify garage :D']);
        });
    }

    private function handleGuestParking($plate)
    {
        return DB::transaction(function () use ($plate) {

            if ($this->checkPublicSpotAvailability()) {
                $this->mqttService->publish('garage/entry_gate', 'full');
                $this->mqttService->publish('garage/entry/display/message', "Garage is full.\nVisit us later.");
                return  response()->json(['status' => 'full','message'=> "Garage is full.\nVisit us later."]);
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
                $this->mqttService->publish('garage/entry_gate', 'open');
                // return response()->json(['error' => 'Guest Already In Garage'],422);
                $this->mqttService->publish('garage/entry/display/message', "Please, enter garage before gate closes.");
                return  response()->json(data: ['status' => 'success','message'=> "Please, enter garage before gate closes."]);
            }

            if ($guestPlate->counter >= 3) {
                $this->mqttService->publish('garage/entry_gate', 'limit_exceeded');
                // return response()->json(['error'=>'Guest parking limit reached']);
                $this->mqttService->publish('garage/entry/display/message', "Guest limit reached.\nPlease register your car on our application.");
                return  response()->json(data: ['status' => 'limit_exceeded','message'=> "Guest limit reached.\nPlease register your car on our application."]);
            }

            $guestPlate->increment('counter');
            return $this->logGuestParking($guestPlate);
        });
    }

    private function logGuestParking($guestPlate)
    {
        return DB::transaction(function () use ($guestPlate) {
            $this->checkOrCreateLog(Guest_Spot_Log::class, $guestPlate->license_plate, 'Public Spot', $this->entered_at,null);
            return response()->json(['status' => 'success','message'=> 'Welcome to parkify garage :D']);
            // return response()->json(['success' => 'Guest car parked']);
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
        $publicSpots = Public_Spot::count();
        $reservableSpots = Reservable_Spot::where(['is_occupied',0],['location_id',1])->count();
        // Publish to MQTT
        $this->mqttService->publish('garage/available_spots', $publicSpots - $count.' '.$reservableSpots);
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
                return $this->processExit($user, Reservable_Spot_Log::class, $userPlate->plate );
            }
            return $this->processExit($user, Public_Spot_Log::class, $userPlate->plate, 'public');
        });
    }

    private function handleGuestExit($plate)
    {
        return DB::transaction(function () use ($plate) {
            $guestPlate = Guest::where('license_plate', $plate)->first();
            if (!$guestPlate) {
                $this->mqttService->publish('garage/exit/display/message', "Guest has no entry logs or plate is misread.");
                return response()->json(['status' => 'error','message'=> 'Guest has no entry logs or plate is misread.']);
                // return response()->json(['error'=>'Guest has no entry logs or plate is misread'],422);
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
                    ['exited_at', '>=', now()->subMinutes(10)]
                ])->orderBy('exited_at', 'desc')->first();
                if ($currentLog) {
                    $this->mqttService->publish('garage/exit_gate', 'open');
                    $this->mqttService->publish('garage/exit/display/message', "User already paid for exit.");
                    return response()->json(['status' => 'success','message'=> 'User already paid for exit.']);
                    // return response('User already paid for exit');
                }
                $this->mqttService->publish('garage/exit/display/message', "User has no entry logs or plate is misread.");
                return response()->json(['status' => 'error','message'=> 'User has no entry logs or plate is misread.']);
                // return response('User has no entry logs');
            }
            if($spotType){
                $entry = $currentLog->entered_at;
            }else{
                $entry = $user->activeReservation->expected_arrival;
            }
            $parkingTime = round($entry->floatDiffInHours($this->exitTime), 2);
            $parkingPrice = Spot_Management::where('type', $spotType ?: 'reservable')->first();
            if(!$user){
                $hourPrice = $parkingPrice->price_per_hour + $parkingPrice->additional_guest_fees;
            }
            else{
                $hourPrice = $parkingPrice->price_per_hour;
            }
            $invoice = $parkingTime * $hourPrice;
            $data = [
                'invoice_price' => $invoice,
                'exited_at' => $this->exitTime
            ];
            if ($user && $deductBalance) {
                $balance = $user->userData->balance;
                $status = null;
//                $balance < $invoice ? $this->sendSms("You don't have enough balance to pay for spot. Your invoice is $invoice and your current balance is $balance.", $user->userData->phone) : $status = true;
                $balance < $invoice ? $status = null : $status = true;
                if ($status == null){
                    // return response('Insufficient balance.' . $invoice);
                    $this->mqttService->publish('garage/exit/display/message', "Insufficient balance.\nMake sure you have $invoice on your account");
                    return response()->json(['status' => 'error','message'=> "Insufficient balance.\nMake sure you have $invoice on your account"]);
                }
            // Code discount logic here
                $userGift = User_Gift::where([['is_active',1],
                    ['user_id',$user->id]])
                    ->first();
                if ($userGift) {
                    $invoice = $invoice * (100 - (float) $userGift->gift->discount_percentage) / 100;
                    $data['invoice_price']=$invoice;
                }
                $userPoints = (int) round($parkingPrice->points_per_hour *  $parkingTime);

                try {
                    DB::transaction(function () use ($currentLog,$user,$userGift,$data,$invoice,$spotType,$userPoints) {
                        $user->userData()->decrement('balance', $invoice);
                        $user->userData()->increment('points', $userPoints);
                        if(!$spotType){
                            $user->activeReservation()->update(['is_active' => 0]);
                            $user->activeReservation->reservableSpot->update(['is_occupied' => 0]);
                        }
                        if($userGift){
                            $userGift->update(['applied_to_payment' => 0,'is_active' => 0]);
                        }
                        $data['is_payed'] = 1;
                        $currentLog->update($data);
                    });
                } catch (\Exception $e){
                    return response()->json(['error'=>$e->getMessage()],422);
                }
                Mqtt_Spot_Log::where('license_plate', $plate)->delete();
                $this->mqttService->publish('garage/exit_gate', 'open');
                $this->logAndPublish(null,'Public Spot',false);
            } else {
                $guestPayment = (new PaymentController())->guestPayment($invoice, $currentLog->license_plate);
                if ($guestPayment) {
                    $qrPath = QrCode::format('svg')
                        ->size(450)
                        ->margin(1)
                        ->errorCorrection('H')
                        ->generate($guestPayment);

                    $s3Path = 'qrcodes/' . uniqid() . '.svg';
                    Storage::disk('filebase')->put($s3Path, $qrPath);
                    // $paymentUrl = Storage::disk('filebase')->url($s3Path);
                    $paymentUrl = Storage::disk('filebase')->temporaryUrl(
                        $s3Path,
                        now()->addMinutes(60)
                    );
                    $this->mqttService->publish('garage/exit/display/qrcode', $paymentUrl);
                    $data['qr_payment'] = $paymentUrl;
                    $currentLog->update($data);
                    // return response('Guest payment qrcode generated successfully. ' . $paymentUrl);
                    return response()->json(['status' => 'pending','message'=> 'Guest payment qrcode generated successfully.' ,'payment_qr'=>$paymentUrl]);
                }
            }
            $message = "Plate:$plate \nFees:$invoice \nGoodbye :)";
            $this->mqttService->publish('garage/exit/display/message',$message);
            // return response("{$invoice} " . ($user ? ($spotType ? 'PUBLIC' : 'USER RESERVED') : 'GUEST PUBLIC') . " {$parkingTime}");
            return response()->json(['status' => 'success' , 'message' => $message]);
        });
    }
}
