<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function addLocation(Request $request){
        $request->validate([
            'name' => ['required','unique:locations,name'],
            'address' => ['required','unique:locations,address'],
            'gps_location' => ['required','unique:locations,gps_location'],
        ]);
        $status = Location::create([
            'name' => $request->name,
            'address' => $request->address,
            'gps_location' => $request->gps_location,
        ]);
        if (!$status) {
            return response()->json(['error' => 'Location not created'], 422);
        }
        return response()->json(['success' => 'Location created'], 200);
    }

    public function editLocation(Request $request, $id){
        $request->validate([
            'name' => ['required','unique:locations,name,'.$id],
            'address' => ['required','unique:locations,address,'.$id],
            'gps_location' => ['required','unique:locations,gps_location,'.$id],
        ]);
        $location = Location::find($id);
        if(!$location){
            return response()->json(['error'=>"Location not found."],422);
        }
        $status = $location->update([
            'name' => $request->name,
            'address' => $request->address,
            'gps_location' => $request->gps_location,
        ]);
        if (!$status) {
            return response()->json(['error' => 'Something went wrong while updating your location'], 422);
        }
        return response()->json(['success' => 'Location updated successfully'], 200);
    }

    public function changeLocationStatus(Request $request,$id){
        $request->validate([
            'status'=>['required','in:0,1']
        ]);
        $location = Location::find($id);
        if (!$location) {
            return response()->json(['error' => "Location not found."], 422);
        }
        $status = $location->update(['is_active'=>$request->status]);
        if (!$status) {
            return response()->json(['error' => "Something went while updating location status wrong."], 422);
        }
        return response()->json(['success' => "Location status updated successfully."], 200);
    }

    public function deleteLocation(Request $request){
        $location = Location::find($request->id);
        if(!$location){
            return response()->json(['error'=>"Location not found."],422);
        }
        $status = $location->delete();
        if (!$status) {
            return response()->json(['error' => 'Something went wrong while deleting your location'], 422);
        }
        return response()->json(['success' => 'Location deleted successfully'], 200);
    }
}
