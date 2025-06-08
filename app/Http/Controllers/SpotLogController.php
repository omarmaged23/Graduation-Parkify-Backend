<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\NotificationTrait;
use App\Models\Guest;
use App\Models\Guest_Spot_Log;
use App\Models\License_Plate;
use App\Models\Location;
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

    // MQTT Topic Templates (will be formatted with branch)
    private $ENTRY_GATE = 'garage/%s/entry_gate';
    private $EXIT_GATE = 'garage/%s/exit_gate';
    private $ENTRY_DISPLAY = 'garage/%s/entry/display/message';
    private $EXIT_DISPLAY = 'garage/%s/exit/display/message';
    private $EXIT_QR = 'garage/%s/exit/display/qrcode';
    private $AVAILABLE_SPOTS = 'garage/%s/available_spots';
    private $BLOCKER_CONTROL = 'garage/%s/spot/blocker';

    // Spot Type Constants
    private $PUBLIC_SPOT = 'Public Spot';
    private $RESERVABLE_SPOT = 'Reservable Spot';

    // MQTT Messages
    private $WELCOME_MSG = 'Welcome to parkify garage :D';
    private $GARAGE_FULL_MSG = "Garage is full.\nVisit us later.";
    private $EARLY_ARRIVAL_MSG = 'User have active reservation but arrived earlier than expected, you can enter garage 15 minutes earlier the reservation or you are not authorized';
    private $GUEST_LIMIT_MSG = "Guest limit reached.\nPlease register your car on our application.";
    private $ENTER_BEFORE_CLOSE_MSG = "Please, enter garage before gate closes.";
    private $NO_ENTRY_LOGS_MSG = 'Guest has no entry logs or plate is misread.';
    private $ALREADY_PAID_MSG = 'User already paid for exit.';

    protected $mqttService;
    protected $entered_at;
    protected $exitTime;
    protected $branch;
    protected $branchID;

    public function __construct()
    {
        $this->mqttService = new MqttService();
    }

    private function checkLocationExistence($branch){
        return Location::where('name',$branch)->value('id');
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
    public function parkCar(Request $request, $branch)
    {
        $branchID = $this->checkLocationExistence($branch);
        if($branchID){
            $this->branch = $branch;
            $this->branchID = $branchID;
        }else{
            return response()->json(['status' => 'error' , 'message' => 'location not found']);
        }

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
            $locationCondition = $activeReservation->reservableSpot->location_id  == $this->branchID;

            if ($activeReservation && $locationCondition && $user->activeReservationWithinLimit) {
                return $this->parkInReservedSpot($activeReservation, $userPlate->plate, $user->id);
            } else if ($activeReservation && !$user->activeReservationWithinLimit && $locationCondition) {
                $this->mqttService->publish(sprintf($this->ENTRY_DISPLAY, $this->branch), $this->EARLY_ARRIVAL_MSG);
                return response()->json(['status' => 'error' , 'message' => $this->EARLY_ARRIVAL_MSG]);
            } else {
                return $this->parkInPublicSpot($userPlate->plate, $user->id);
            }
        });
    }

    private function checkOrCreateLog($modelClass, $plate, $type, $enteredAt, $user_id ,$reservable_spot_id = null)
    {
        if(!$user_id){
            $condition = [
                ['license_plate', '=', $plate],
                ['is_payed', '=', 0],
                ['location_id', '=', $this->branchID]
            ];
            $data = [
                'license_plate' => $plate,
                'entered_at' => $enteredAt,
                'location_id' => $this->branchID
            ];
        } else {
            $condition = [
                ['license_plate', '=', $plate],
                ['is_payed', '=', 0],
                ['user_id', '=', $user_id],
//                ['location_id', '=', $this->branchID]
            ];
            $data = [
                'license_plate' => $plate,
                'entered_at' => $enteredAt,
                'user_id' => $user_id,
//                'location_id' => $this->branchID
            ];
            if($type == $this->PUBLIC_SPOT){
                $condition[] = ['location_id', '=', $this->branchID];
                $data[] = ['location_id', '=', $this->branchID];
            }
        }
        $log = $modelClass::where($condition)
            ->whereDate('entered_at', Carbon::today())->orderBy('entered_at', 'desc')->first();

        if (!$log) {
            $reservable_spot_id ? $data['reservable_spot_id'] = $reservable_spot_id : $reservable_spot_id = null;

            $modelClass::create($data);
            // Log and publish
            $this->logAndPublish($plate, $type, true);
        } else {
            $this->logAndPublish($plate, $type);
        }
        $this->openEntryGateWithWelcome();
    }

    private function parkInReservedSpot($reservation, $plate,$user_id)
    {
        return DB::transaction(function () use ($reservation, $plate,$user_id) {
            $this->checkOrCreateLog(Reservable_Spot_Log::class, $plate, $this->RESERVABLE_SPOT, $this->entered_at,$user_id ,$reservation->reservable_spot_id);
            return response()->json(['status' => 'success','message'=> $this->WELCOME_MSG]);
        });
    }

    private function checkPublicSpotAvailability()
    {
        $allSpots = Public_Spot::where('location_id', $this->branchID)->count();
        $usedSpots = Mqtt_Spot_Log::LocationCount($this->PUBLIC_SPOT,$this->branch);
        return $allSpots == $usedSpots;
    }

    private function parkInPublicSpot($plate,$user_id)
    {
        return DB::transaction(function () use ($plate,$user_id) {
            if ($this->checkPublicSpotAvailability()) {
                $this->handleFullGarage();
                return response()->json(['error'=>'Not enough spots'],422);
            }
            $this->checkOrCreateLog(Public_Spot_Log::class, $plate, $this->PUBLIC_SPOT, $this->entered_at,$user_id);
            return response()->json(['status' => 'success','message'=> $this->WELCOME_MSG]);
        });
    }

    private function handleGuestParking($plate)
    {
        return DB::transaction(function () use ($plate) {

            if ($this->checkPublicSpotAvailability()) {
                $this->handleFullGarage();
                return  response()->json(['status' => 'full','message'=> $this->GARAGE_FULL_MSG]);
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
            if (Mqtt_Spot_Log::where([['license_plate', '=' ,$guestPlate->license_plate],['location','=',$this->branch]])->exists()) {
                $this->mqttService->publish(sprintf($this->ENTRY_GATE, $this->branch), 'open',false);
                $this->mqttService->publish(sprintf($this->ENTRY_DISPLAY, $this->branch), $this->ENTER_BEFORE_CLOSE_MSG);
                return  response()->json(data: ['status' => 'success','message'=> $this->ENTER_BEFORE_CLOSE_MSG]);
            }

            if ($guestPlate->counter >= 3) {
                $this->mqttService->publish(sprintf($this->ENTRY_GATE, $this->branch), 'limit_exceeded',false);
                $this->mqttService->publish(sprintf($this->ENTRY_DISPLAY, $this->branch), $this->GUEST_LIMIT_MSG);
                return  response()->json(data: ['status' => 'limit_exceeded','message'=> $this->GUEST_LIMIT_MSG]);
            }

            $guestPlate->increment('counter');
            return $this->logGuestParking($guestPlate);
        });
    }

    private function logGuestParking($guestPlate)
    {
        return DB::transaction(function () use ($guestPlate) {
            $this->checkOrCreateLog(Guest_Spot_Log::class, $guestPlate->license_plate, $this->PUBLIC_SPOT, $this->entered_at,null);
            return response()->json(['status' => 'success','message'=> $this->WELCOME_MSG]);
        });
    }

    // Reusable functions for common MQTT patterns
    private function handleFullGarage()
    {
        $this->mqttService->publish(sprintf($this->ENTRY_GATE, $this->branch), 'full',false);
        $this->mqttService->publish(sprintf($this->ENTRY_DISPLAY, $this->branch), $this->GARAGE_FULL_MSG);
    }

    private function openEntryGateWithWelcome()
    {
        $this->mqttService->publish(sprintf($this->ENTRY_GATE, $this->branch), 'open',false);
        $this->mqttService->publish(sprintf($this->ENTRY_DISPLAY, $this->branch), $this->WELCOME_MSG);
    }

    private function logAndPublish($licensePlate, $type, $log = false)
    {
        if ($log) {
            // Insert into mqtt_spot_log
            Mqtt_Spot_Log::create([
                'license_plate' => $licensePlate,
                'type' => $type,
                'location' => $this->branch,
                'entered_at' => $this->entered_at
            ]);
        }

        $count = Mqtt_Spot_Log::LocationCount($this->PUBLIC_SPOT,$this->branch);
        $publicSpots = Public_Spot::where('location_id', $this->branchID)->count();
        $reservableSpots = Reservable_Spot::where([['is_occupied',0],['location_id',$this->branchID]])->count();
        // Publish to MQTT
        $this->mqttService->publish(sprintf($this->AVAILABLE_SPOTS, $this->branch), $publicSpots - $count.' '.$reservableSpots);
    }

    /**
     * NOW HANDLE EXIT LOGIC
     */

    public function exitParking(Request $request, $branch)
    {
        $branchID = $this->checkLocationExistence($branch);
        if($branchID){
            $this->branch = $branch;
            $this->branchID = $branchID;
        }else{
            return response()->json(['status' => 'error' , 'message' => 'location not found']);
        }
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
            if ($activeReservation && $activeReservation->reservableSpot->location_id  == $this->branchID) {
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
                $this->mqttService->publish(sprintf($this->EXIT_DISPLAY, $this->branch), $this->NO_ENTRY_LOGS_MSG);
                return response()->json(['status' => 'error','message'=> $this->NO_ENTRY_LOGS_MSG]);
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
            // Mutual condition for both public and reservable spots
            $dataCondition = [
                ['license_plate', $plate],
                ['is_payed', 0]
            ];
            // Added condition for public spots
            if($spotType){
                $dataCondition[] = ['location_id', $this->branchID];
            }
            $currentLog = $logModel::where($dataCondition)->orderBy('entered_at', 'desc')->first();

            if (!$currentLog) {
                // Mutual condition for both public and reservable spots
                $dataCondition = [
                    ['license_plate', $plate],
                    ['is_payed', 1],
                    ['exited_at', '>=', now()->subMinutes(10)]
                ];
                // Added condition for public spots
                if($spotType){
                    $dataCondition[] = ['location_id', $this->branchID];
                }
                $currentLog = $logModel::where($dataCondition)->orderBy('exited_at', 'desc')->first();
                if ($currentLog) {
                    $this->mqttService->publish(sprintf($this->EXIT_GATE, $this->branch), 'open',false);
                    $this->mqttService->publish(sprintf($this->EXIT_DISPLAY, $this->branch), $this->ALREADY_PAID_MSG);
                    return response()->json(['status' => 'success','message'=> $this->ALREADY_PAID_MSG]);
                }
                $this->mqttService->publish(sprintf($this->EXIT_DISPLAY, $this->branch), $this->NO_ENTRY_LOGS_MSG);
                return response()->json(['status' => 'error','message'=> $this->NO_ENTRY_LOGS_MSG]);
            }
            if($spotType){
                $entry = $currentLog->entered_at;
            }else{
                $entry = $user->activeReservation->expected_arrival;
                $this->BLOCKER_CONTROL = sprintf($this->BLOCKER_CONTROL, $this->branch);
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
                $balance < $invoice ? $this->sendSms("You don't have enough balance to pay for spot. Your invoice is $invoice and your current balance is $balance.", $user->userData->phone) : $status = true;
                if ($status == null){
                    $this->mqttService->publish(sprintf($this->EXIT_DISPLAY, $this->branch), "Insufficient balance.\nMake sure you have $invoice on your account");
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
                Mqtt_Spot_Log::where('license_plate', $plate)
                    ->orderBy('entered_at', 'desc')
                    ->first()
                    ?->delete();

                if(!$spotType){
                    $blockerMsg = [
                        'spot_code' => $user->activeReservation->reservableSpot->spot_code,
                        'status' => 'close'
                    ];
                    $this->mqttService->publish($this->BLOCKER_CONTROL,json_encode($blockerMsg));
                }

                $this->mqttService->publish(sprintf($this->EXIT_GATE, $this->branch), 'open',false);
                $this->logAndPublish(null,$this->PUBLIC_SPOT,false);
            } else {
                $guestPayment = (new PaymentController())->guestPayment($invoice, $currentLog->license_plate, $this->branch);
                if ($guestPayment) {
                    $qrPath = QrCode::format('svg')
                        ->size(450)
                        ->margin(1)
                        ->errorCorrection('H')
                        ->generate($guestPayment);

                    $s3Path = 'qrcodes/' . uniqid() . '.svg';
                    Storage::disk('filebase')->put($s3Path, $qrPath);
                    $paymentUrl = Storage::disk('filebase')->temporaryUrl(
                        $s3Path,
                        now()->addMinutes(60)
                    );
                    $this->mqttService->publish(sprintf($this->EXIT_QR, $this->branch), $paymentUrl);
                    $data['qr_payment'] = $paymentUrl;
                    $currentLog->update($data);
                    $this->mqttService->publish(sprintf($this->EXIT_DISPLAY, $this->branch),"Your QR code is ready!.\nKindly scan and pay to exit garage");
                    return response()->json(['status' => 'pending','message'=> 'Guest payment qrcode generated successfully.' ,'payment_qr'=>$paymentUrl]);
                }
            }
            $message = "Plate:$plate \nFees:$invoice \nGoodbye :)";
            $this->mqttService->publish(sprintf($this->EXIT_DISPLAY, $this->branch),$message);
            return response()->json(['status' => 'success' , 'message' => $message]);
        });
    }
}
