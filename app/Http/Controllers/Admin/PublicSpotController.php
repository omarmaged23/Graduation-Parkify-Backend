<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Public_Spot;
use Illuminate\Http\Request;

class PublicSpotController extends Controller
{
    public function getPublicSpots(){
        $spots = Public_Spot::all();
        if (!$spots) {
            return response()->json(['error' => 'Public spots not found']);
        }
        return response()->json(['success' => $spots]);
    }
    public function addPublicSpot(Request $request){
        $request->validate([
            'spot_code' => ['required','unique:public__spots,spot_code'],
            'management_id' => ['required','exists:spot__management,id'],
            'location_id' => ['required','exists:locations,id'],
        ]);

        $status = Public_Spot::create([
            'spot_code' => $request->spot_code,
            'management_id' => $request->management_id,
            'location_id' => $request->location_id,
        ]);

        if($status){
            return response()->json(['success'=>"Successfully added new public spot."],200);
        }

        return response()->json(['error'=>"Something went wrong while adding public spot."],422);
    }

    public function editPublicSpot(Request $request,$spot_id){
        $request->validate([
            'spot_code' => ['required','unique:public__spots,spot_code,'.$spot_id],
            'management_id' => ['required','exists:spot__management,id'],
            'location_id' => ['required','exists:locations,id'],
        ]);
        $spot = Public_Spot::find($spot_id);
        if(!$spot){
            return response()->json(['error'=>"No public spot found."],422);
        }
        $status = $spot->update([
            'spot_code' => $request->spot_code,
            'management_id' => $request->management_id,
            'location_id' => $request->location_id
        ]);
        if($status){
            return response()->json(['success'=>"Successfully updated public spot."],200);
        }

        return response()->json(['error'=>"Something went wrong while updating public spot."],422);
    }

    public function deletePublicSpot(Request $request){
        $spot = Public_Spot::find($request->id);
        if(!$spot){
            return response()->json(['error'=>"No public spot found."],422);
        }
        $status = $spot->delete();
        if($status){
            return response()->json(['success'=>"Successfully deleted public spot."],200);
        }
        return response()->json(['error' => 'Something went wrong while deleting public spot.'],422);
    }
}
