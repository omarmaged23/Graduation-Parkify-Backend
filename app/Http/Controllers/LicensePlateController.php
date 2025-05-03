<?php

namespace App\Http\Controllers;

use App\Models\License_Plate;
use Illuminate\Http\Request;

class LicensePlateController extends Controller
{
    public function getUserPlates(){
        $plates = License_Plate::where('user_id',auth('api')->user()->id)->select('id','plate')->get();
        if(!$plates){
            return response()->json(['error'=>'no plates found'],422);
        }
        return response()->json(['success'=>$plates],200);
    }
    public function addLicensePlate(Request $request){
        $request->validate([
            'plate' => ['required','string','unique:license__plates,plate'],
        ]);
        $status = auth('api')->user()->licensePlates()->create([
            'plate' => $request->plate
        ]);
        if (!$status) {
            return response()->json(['error' => 'Something went wrong adding the plate'], 422);
        }
        return response()->json(['success' => 'License Plate added successfully.'], 200);
    }

    public function deleteLicensePlate(Request $request){
        $request->validate([
            'id' => ['required','integer','exists:license__plates,id'],
        ]);
        $plate = License_Plate::where([['id',$request->id],['user_id'=>auth('api')->user()->id]])->first();
        if (!$plate) {
            return response()->json(['error' => 'Plate not found'], 422);
        }
        $status = $plate->delete();
        if (!$status) {
            return response()->json(['error' => 'Something went wrong deleting the plate'], 422);
        }
        return response()->json(['success' => 'License Plate deleted successfully.'], 200);
    }
}
