<?php

namespace App\Http\Controllers\Admin;

use App\Events\AdminActionPerformed;
use App\Http\Controllers\Controller;
use App\Models\Gift;
use Illuminate\Http\Request;

class GiftController extends Controller
{
    public function addGift(Request $request){
        $auth = auth('admin')->user();
        $request->validate([
            'description' => 'required',
            'cost' => ['required','integer'],
            'discount' => ['required','numeric','regex:/^\d+(\.\d{1,2})?$/'],
        ]);
        if ($request->discount > 50){
            return response()->json(['error'=>"Discount cannot be more than 50% off"],422);
        }
        $status = Gift::create([
            'description' => $request->description,
            'cost' => $request->cost,
            'discount_percentage' => $request->discount,
        ]);
        if(!$status){
            return response()->json(['error'=>"Something went wrong while adding gift."],422);
        }
        event(new AdminActionPerformed($auth->name,$auth->email,"Added a gift to system $status->description",$auth->role));

        return response()->json(['success'=>"Gift added successfully."],200);
    }

    public function editGift(Request $request,$id){
        $request->validate([
            'description' => 'required',
            'cost' => ['required','integer'],
            'discount' => ['required','numeric','regex:/^\d+(\.\d{1,2})?$/']
        ]);
        $gift = Gift::find($id);
        if(!$gift){
            return response()->json(['error'=>"Gift not found."],422);
        }
        if ($request->discount > 50){
            return response()->json(['error'=>"Discount cannot be more than 50% off"],422);
        }
        $status = $gift->update([
            'description' => $request->description,
            'cost' => $request->cost,
            'discount_percentage' => $request->discount,
        ]);
        if(!$status){
            return response()->json(['error'=>"Something went wrong while updating gift."],422);
        }
        $auth = auth('admin')->user();
        event(new AdminActionPerformed($auth->name,$auth->email,"Edited system gift $status->description",$auth->role));

        return response()->json(['success'=>"Gift updated successfully."],200);
    }

    public function deleteGift(Request $request){
        $gift = Gift::find($request->id);
        if(!$gift){
            return response()->json(['error'=>"Gift not found."],422);
        }
        $status = $gift->delete();
        if(!$status){
            return response()->json(['error'=>"Something went wrong while deleting gift."],422);
        }
        $auth = auth('admin')->user();
        event(new AdminActionPerformed($auth->name,$auth->email,"Deleted system gift $gift->description with discount $gift->percentage %",$auth->role));
        return response()->json(['success'=>"Gift deleted successfully."],200);
    }

    public function getAllGifts(){
        $gifts = Gift::all();
        if(!$gifts){
            return response()->json(['error'=>"Gifts not found."],422);
        }
        return response()->json(['success'=>$gifts],200);
    }

    public function getGift($id){
        $gift = Gift::find($id);
        if(!$gift){
            return response()->json(['error'=>"Gift not found."],422);
        }
        return response()->json(['success'=>$gift],200);
    }

}
