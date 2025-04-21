<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Spot_Management;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpotManagementController extends Controller
{
    public function getSpotDetails()
    {
        $details = Spot_Management::all();
        if(!$details){
            return response()->json(['success' => 'N/A']);
        }
        return response()->json(['success' => $details]);
    }
    public function getPointsPerHour()
    {
        $details = Spot_Management::pluck('points_per_hour','type');
        if(!$details){
            return response()->json(['success' => 'N/A']);
        }
        return response()->json(['success' => $details]);
    }
    public function editPointsPerHour(Request $request)
    {
        $request->validate([
            'public_points_per_hour' => ['required','integer'],
            'reservable_points_per_hour' => ['required','integer'],
        ]);

        $public = Spot_Management::where('type','public')->first();
        $reservable = Spot_Management::where('type','reservable')->first();

        try {
            DB::transaction(function () use ($request, $public, $reservable) {
                $public->update(['points_per_hour'=>$request->public_points_per_hour]);
                $reservable->update(['points_per_hour'=>$request->reservable_points_per_hour]);
            });
        } catch (\Exception $e){
            return response()->json(['error'=>'Public and Reservable Spots not found. '. $e->getMessage()]);
        }
        return response()->json(['success'],200);
    }
    public function managePrices(Request $request,$type)
    {
        // handle reservable spot type
        if($type == 'reservable'){
            $request->validate([
                'price_per_hour' => ['required','numeric','regex:/^\d+(\.\d{1,2})?$/'],
                'reservation_fees' => ['required','numeric','regex:/^\d+(\.\d{1,2})?$/'],
                'time_restriction' => ['required','integer'],
//                'points_per_hour' => ['required','integer'],
            ]);
            $status = Spot_Management::updateOrCreate([
                'type' => $type,
            ],
            [
                'price_per_hour' => $request->price_per_hour,
                'reservation_fees' => $request->reservation_fees,
                'time_restriction' => $request->time_restriction,
//                'points_per_hour' => $request->points_per_hour,
            ]);
            if($status){
                return response()->json(['success'=>'Reservation prices updated successfully.'],200);
            }
            return response()->json(['error'=>"Something went wrong while updating prices."],422);
        }

        // if not reservable spot, that means it's public now handle the logic

        $request->validate([
            'price_per_hour' => ['required','numeric','regex:/^\d+(\.\d{1,2})?$/'],
            'additional_guest_fees' => ['required','numeric','regex:/^\d+(\.\d{1,2})?$/'],
//            'points_per_hour' => ['required','integer'],
        ]);
        $status = Spot_Management::updateOrCreate([
            'type' => 'public',
        ],
        [
            'price_per_hour' => $request->price_per_hour,
            'additional_guest_fees' => $request->additional_guest_fees,
//            'points_per_hour' => $request->points_per_hour,
        ]);
        if($status){
            return response()->json(['success'=>'Public prices updated successfully.'],200);
        }
        return response()->json(['error'=>"Something went wrong while updating prices."],422);
    }
}
