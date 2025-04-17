<?php

namespace App\Http\Controllers;

use App\Models\Reservable_Spot;
use App\Models\Spot_Management;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    public function reserveSpot(Request $request){
        // validate fields are not empty and date is in right format
        $request->validate([
            'plate' => 'required',
            'reserve_at' => 'required|date_format:Y-m-d H:i:s',
        ]);

        // reservation time >= now + 1 hour --- Proceed
        $reservationTimeStamp= Carbon::parse($request->reserve_at);
        $currentTime = Carbon::now();
        $minAllowedTime = $currentTime->copy()->addHour();
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
        $plate = auth()->user()->licensePlates->where('plate',$request->plate);
        if(!$plate){
            return response()->json(['error'=>'plate not found'],422);
        }

        // check if this plate has an active reservation
        $acitvePlateReservation = auth()->user()->reservations->where('is_active',1)->where('license_plate',$request->plate);
        if($acitvePlateReservation){
            return response()->json(['error'=>'plate already has reservation'],422);
        }
        // now check if there is available spots to reserve
        $activeReservations = Reservable_Spot::where('is_occupied',1)->count();
        $reservableSpots = Reservable_Spot::count();
        if($activeReservations >= $reservableSpots){
            return response()->json(['error'=>'all spots are reserved'],422);
        }

        // now make sure user has enough balance in his account
        $hourDifference = $currentTime->floatDiffInHours($reservationTimeStamp);
        $reservationFees = Spot_Management::where('type','reservable')->first()->reservation_fees;
        $reservationFees*=$hourDifference;

        $userBalance = auth()->user()->userData->balance;
        if($userBalance < $reservationFees){
            return response()->json(['error'=>'please add more balance to your account'],422);
        }
        // Otherwise deduct fees and confirm
        $transaction = DB::transaction(function () use ($request,$reservationFees,$reservationTimeStamp){
            $balance = auth()->user()->userData()->decrement('balance',$reservationFees);
            $spot = Reservable_Spot::where('is_occupied',0)->first();
            $reservation = $spot->reservations()->create([
                'license_plate' => $request->plate,
                'expected_arrival' => $request->reserve_at,
                'user_id' => auth()->user()->id,
            ]);
            $spot->update(['is_occupied' => 1]);
            if(!$reservation | !$balance){
                return response()->json(['error'=>'reservation not created, something went wrong'],422);
            }
            return response()->json(['success'=>$reservation,'spot'=>$spot,'reservation_time'=> $reservationTimeStamp->format('F j \a\t g A')],200);
        });
        return $transaction;
    }
}
