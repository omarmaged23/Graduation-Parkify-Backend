<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Public_Spot_Log;
use App\Models\Public_Spot_Used;
use App\Models\PublicSpotUsed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicSpotLogController extends Controller
{
    public function getPublicSpotLog()
    {
        return auth('api')->user()->publicSpotLogs()
        ->limit(10)
        ->get()
        ->map(function ($spot) {
            return $spot->toArray() + ['spot_code' => 'Public Spot'];
        });
    }

    public function logUsedPublicSpot(Request $request , $location){
        $request->validate([
            'spot_code' => 'required|exists:public__spots,spot_code',
        ]);
        $location = Location::select('id','name')->where('name',$location)->first();
        if(!$location){
            return response()->json(['status' => 'error','message' => 'location not found'], 422);
        }

        $log = Public_Spot_Used::create([
            'spot_code' => $request->spot_code,
            'location_id' => $location->id
        ]);
        if($log){
            return response()->json(['status' => 'success'], 200);
        }
        return response()->json(['status' => 'error'], 422);
    }
}
