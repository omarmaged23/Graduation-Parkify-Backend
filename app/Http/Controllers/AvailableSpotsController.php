<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Mqtt_Spot_Log;
use App\Models\Public_Spot;
use App\Models\Reservable_Spot;
use Illuminate\Http\Request;

class AvailableSpotsController extends Controller
{
    public function getAvailableSpots($id)
    {
        $location = Location::find($id);
        if(!$location){
            return response()->json(['error'=>'Location not found'], 404);
        }
        $occupiedSpots = Mqtt_Spot_Log::LocationCount('Public Spot',$location->name);
        $publicSpots = Public_Spot::where('location_id', $id)->count();
        $reservableSpots = Reservable_Spot::where([['is_occupied',0],['location_id',$id]])->count();
        return response()->json(['success'=>$publicSpots-$occupiedSpots.' '.$reservableSpots]);
    }
}
