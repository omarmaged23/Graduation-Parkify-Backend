<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Reservable_Spot;
use App\Services\MqttService;
use Illuminate\Http\Request;

class ReservableSpotController extends Controller
{
    public function getReservableSpots(){
        $spots = Reservable_Spot::all();
        if (!$spots) {
            return response()->json(['error' => 'Reservable spots not found']);
        }
        return response()->json(['success' => $spots]);
    }
    public function addReservableSpot(Request $request)
    {
        $request->validate([
            'spot_code' => ['required','unique:reservable__spots,spot_code'],
            'management_id' => ['required','exists:spot__management,id'],
            'location_id' => ['required','exists:locations,id'],
        ]);
        $location = Location::find($request->location_id)->name;
        $status = Reservable_Spot::create([
            'spot_code' => $request->spot_code,
            'management_id' => $request->management_id,
            'location_id' => $request->location_id,
        ]);

        if($status){
            $spot = [
                'spot_code' => $status->spot_code,
                'type' => 'reservable'
            ];
            (new MqttService())->publish(sprintf('garage/%s/spots/add',$location),$spot,false);
            return response()->json(['success'=>"Successfully added new reservable spot."],200);
        }

        return response()->json(['error'=>"Something went wrong while adding reservable spot."],422);
    }

    public function editReservableSpot(Request $request,$spot_id){
        $request->validate([
            'spot_code' => ['required','unique:reservable__spots,spot_code,'.$spot_id],
            'management_id' => ['required','exists:spot__management,id'],
            'location_id' => ['required','exists:locations,id'],
        ]);
        $spot = Reservable_Spot::find($spot_id);
        if(!$spot){
            return response()->json(['error'=>"No reservable spot found."],422);
        }
        $status = $spot->update([
            'spot_code' => $request->spot_code,
            'management_id' => $request->management_id,
            'location_id' => $request->location_id
        ]);
        if($status){
            return response()->json(['success'=>"Successfully updated reservable spot."],200);
        }

        return response()->json(['error'=>"Something went wrong while updating reservable spot."],422);
    }

    public function deleteReservableSpot(Request $request){
        $spot = Reservable_Spot::find($request->id);
        if(!$spot){
            return response()->json(['error'=>"No reservable spot found."],422);
        }
        $location = Location::find($spot->location_id)->name;
        $status = $spot->delete();
        if($status){
            (new MqttService())->publish(sprintf('garage/%s/spots/delete',$location),$spot->spot_code,false);
            return response()->json(['success'=>"Successfully deleted reservable spot."],200);
        }
        return response()->json(['error' => 'Something went wrong while deleting reservable spot.'],422);
    }
}
