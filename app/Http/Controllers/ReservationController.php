<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\NotificationTrait;
use App\Models\Location;
use App\Models\Mqtt_Spot_Log;
use App\Models\Public_Spot;
use App\Models\Reservable_Spot;
use App\Models\Spot_Management;
use App\Services\MqttService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    use NotificationTrait;
    private $AVAILABLE_SPOTS = 'garage/%s/available_spots';
    private $PUBLIC_SPOT = 'Public Spot';
    private $RESERVABLE_SPOT = 'Reservable Spot';

    private function updateAvailableSpots($location){
        $count = Mqtt_Spot_Log::LocationCount($this->PUBLIC_SPOT,$location->name);
        $publicSpots = Public_Spot::where('location_id', $location->id)->count();
        $reservableSpots = Reservable_Spot::where([['is_occupied',0],['location_id',$location->id]])->count();
        // Publish to MQTT
        (new MqttService())->publish(sprintf($this->AVAILABLE_SPOTS, $location->name), $publicSpots - $count.' '.$reservableSpots);
    }
    public function reserveSpot(Request $request){
        // validate fields are not empty and date is in right format
        $request->validate([
            'plate' => 'required',
//            'location_id' => 'required|exists:locations,id',
            'reserve_at' => 'required|date_format:Y-m-d H:i:s',
        ]);
        $location = Location::find($request->location_id);
        if(!$location){
            return response()->json(['error' => 'Location not found'], 404);
        }
        $reservableSpot = Spot_Management::where('type','reservable')->first();
        // reservation time >= now + 1 hour --- Proceed
        $reservationTimeStamp= Carbon::parse($request->reserve_at);
        $currentTime = Carbon::now();
        $minAllowedTime = $currentTime->copy()->addMinutes($reservableSpot->time_restriction);
        $maxAllowedTime = $currentTime->copy()->addDay();
        if($reservationTimeStamp < $minAllowedTime){
            return response()->json(['error'=>'reservation time is not valid, you must reserve at least one hour before the intended time'],422);
        }

        // reservation time <= now + 24Hour --- Proceed
        if($reservationTimeStamp > $maxAllowedTime){
            return response()->json([
                'error' => 'Reservation time is too far in the future. You can only reserve up to 24 hours ahead.'
            ], 422);
        }

        // Check if plate belongs to user
        $plate = auth('api')->user()->licensePlates->where('plate',$request->plate);
        if(!$plate){
            return response()->json(['error'=>'plate not found'],422);
        }

        // check if this plate has an active reservation
        $activePlateReservation = auth('api')->user()->reservations->where('is_active',1)->first();
        // $activePlateReservation = auth('api')->user()->reservations->where('is_active',1)->where('license_plate',$request->plate)->first();
        if($activePlateReservation){
            return response()->json(['error'=>'user already has reservation'],422);
        }
        // now check if there is available spots to reserve
        $activeReservations = Reservable_Spot::where([['is_occupied',1],['location_id',$request->location_id]])->count();
        $reservableSpots = Reservable_Spot::count();
        if($activeReservations >= $reservableSpots){
            return response()->json(['error'=>'all spots are reserved'],422);
        }

        // now make sure user has enough balance in his account
        $hourDifference = $currentTime->floatDiffInHours($reservationTimeStamp);
        $reservationFees = $reservableSpot->reservation_fees;
        $reservationFees*=$hourDifference;

        $userBalance = auth('api')->user()->userData->balance;
        if($userBalance < $reservationFees){
            return response()->json(['error'=>'please add more balance to your account'],422);
        }
        // Otherwise deduct fees and confirm
        $transaction = DB::transaction(function () use ($request,$reservationFees,$reservationTimeStamp,$location){
            $balance = auth('api')->user()->userData()->decrement('balance',$reservationFees);
            $spot = Reservable_Spot::where([['is_occupied',0],['location_id',$request->location_id]])->first();
            $reservation = $spot->reservations()->create([
                'license_plate' => $request->plate,
                'expected_arrival' => $request->reserve_at,
                'user_id' => auth('api')->user()->id,
            ]);
            $spot->update(['is_occupied' => 1]);
            if(!$reservation | !$balance){
                return response()->json(['error'=>'reservation not created, something went wrong'],422);
            }
            $this->updateAvailableSpots($location);
            return response()->json(['success'=>$reservation,'spot'=>$spot,'reservation_time'=> $reservationTimeStamp->format('F j \a\t g A')],200);
        });
        return $transaction;
    }
    public function cancelReservation(Request $request){
        $user = auth('api')->user();
        $reservation = $user->activeReservation;
        if(!$reservation){
            return response()->json(['error'=>'reservation not found'],422);
        }
        $spot = $reservation->reservableSpot;
        $checkLogs = Mqtt_Spot_Log::where([['license_plate',$reservation->license_plate],['location',$spot->location->name],['type',$this->RESERVABLE_SPOT]])->exists();
        if($checkLogs){
            return response()->json(['error'=>'user is already in garage.'],422);
        }
        $now = Carbon::now();
        $expectedTime = $reservation->expected_arrival;
        $difference = $now->diffInSeconds($expectedTime,false);
        $total = null;
        if ($difference > 0 ){
            $difference = round(abs($difference) /3600,2);
            $fees = $spot->spotManagement->price_per_hour;
            $total = $difference * $fees;
            $userBalance = $user->userData->balance;
            if ($userBalance < $total){
                $this->sendSms("Not enough balance to cancel reservation.\nMake sure you account has enough credits to cancel reservation.",$user->userData->phone);
                return response()->json(['error'=>'user not enough balance to cancel reservation'],422);
            }
            $user->userData()->decrement('balance',1000);
        }
        try {
            DB::transaction(function () use ($request,$reservation){
                $reservation->reservableSpot()->update([
                    'is_occupied' => 0
                ]);
                $reservation->delete();
            });
            $this->updateAvailableSpots($spot->location);
        } catch (\Exception $e){
            if ($total){
                $user->userData()->increment('balance',$total);
            }
            return response()->json(['error'=>'cancellation failed','message' => $e->getMessage()],422);
        }
        $total = $total ?? 0 ;
        $msg = 'reservation cancelled successfully'.'total = '.$total;
        return response()->json(['success'=> $msg],200);
    }
    public function deactivateReservationBlocker(Request $request)
    {
        $request->validate([
            'location' => 'required|exists:locations,name',
        ]);
        try{
            $activeReservation = auth('api')->user()->activeReservation;
            if(!$activeReservation){
                return response()->json(['error'=>'user has no active reservations'],422);
            }
            $checkLogs = Mqtt_Spot_Log::where([['license_plate',$activeReservation->license_plate],['location',$request->location],['type',$this->RESERVABLE_SPOT]])->exists();
            if(!$checkLogs){
                return response()->json(['error'=>'user must be in garage to deactivate blocker'],422);
            }
            $spot = $activeReservation->reservableSpot->spot_code;
            $mqtt = new MqttService();
            $msg = [
                'spot_code' => $spot,
                'status' => 'open',
            ];
            $mqtt->publish(sprintf('garage/%s/spot/blocker',$request->location),json_encode($msg));
            return response()->json(['success'=>'blocker deactivated successfully'],200);
        } catch (\Exception $exception){
            return response()->json(['error'=>$exception->getMessage()],422);
        }
    }
    public function getActiveReservation(){
        $activeReservation = auth('api')->user()->activeReservation;
        if(!$activeReservation){
            return response()->json(['error'=> 'user has no active reservations'],422);
        }
        return response()->json(['success'=> $activeReservation],200);
    }
}
