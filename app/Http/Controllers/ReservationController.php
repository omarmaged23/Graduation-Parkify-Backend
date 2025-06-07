<?php

namespace App\Http\Controllers;

use App\Models\Reservable_Spot;
use App\Models\Spot_Management;
use App\Services\MqttService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    public function reserveSpot(Request $request){
        // validate fields are not empty and date is in right format
        $request->validate([
            'plate' => 'required',
            'location_id' => 'required|exists:locations,id',
            'reserve_at' => 'required|date_format:Y-m-d H:i:s',
        ]);
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
        $acitvePlateReservation = auth('api')->user()->reservations->where('is_active',1)->first();
        // $acitvePlateReservation = auth('api')->user()->reservations->where('is_active',1)->where('license_plate',$request->plate)->first();
        if($acitvePlateReservation){
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
        $transaction = DB::transaction(function () use ($request,$reservationFees,$reservationTimeStamp){
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
            return response()->json(['success'=>$reservation,'spot'=>$spot,'reservation_time'=> $reservationTimeStamp->format('F j \a\t g A')],200);
        });
        return $transaction;
    }
    public function cancelReservation(Request $request){
        $reservation = auth('api')->user()->activeReservation;
        if(!$reservation){
            return response()->json(['error'=>'reservation not found'],422);
        }
        try {
            DB::transaction(function () use ($request,$reservation){
                $reservation->reservableSpot()->update([
                    'is_occupied' => 0
                ]);
                $reservation->delete();
            });
        } catch (\Exception $e){
            return response()->json(['error'=>'cancellation failed','message' => $e->getMessage()],422);
        }
        return response()->json(['success'=>'reservation cancelled successfully'],200);
    }
    public function deactivateReservationBlocker(Request $request)
    {
        try{
            $spot = auth('api')->user()->activeReservation->reservableSpot->spot_code;
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
