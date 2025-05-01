<?php

namespace App\Http\Controllers;

use App\Models\Gift;
use App\Models\Refund;
use App\Models\User_Gift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserGiftController extends Controller
{
    public function activateGift(Request $request)
    {
        $gift = Gift::find($request->gift_id);
        if(!$gift){
            return response()->json(['error'=>'Gift not found']);
        }
        $user = auth('api')->user();
        $userGift = User_Gift::where([['is_active',1],['user_id',$user->id]])->first();
        if($userGift){
            return response()->json(['error'=>'User already activated a gift']);
        }
        $userPoints = $user->userData->points;
        $giftCost = $gift->cost;
        if($userPoints < $giftCost){
            return response()->json(['error'=>'Insufficient Points']);
        }
        $status = DB::transaction(function () use ($user,$gift, $giftCost, $userPoints){
            $flag1 = $user->userGifts()->create([
                'gift_id' => $gift->id
            ]);
            $flag2 = $user->userData()->decrement('points', $giftCost);
            if($flag1 && $flag2){
                return true;
            }
            return false;
        });
        if($status){
            return response()->json(['success'=>'Gift activated successfully']);
        }
        return response()->json(['error'=>'Something went wrong activating your gift']);
    }
    public function deactivateGift(Request $request){
        $userGift = User_Gift::where([['is_active',1],
            ['user_id',auth('api')->user()->id],
            ['gift_id',$request->gift_id]])
            ->first();
        if(!$userGift){
            return response()->json(['error'=>'User has no activated gifts']);
        }
        $refundPercentage = Refund::first()->percentage;
        if(!$refundPercentage){
            return response()->json(['error'=>'Refund percentage is not available']);
        }
        $refundedAmount = $userGift->gift->cost * ((float) $refundPercentage / 100);
        $refundedAmount = floor($refundedAmount);
        $status = DB::transaction(function () use ($userGift, $refundPercentage, $refundedAmount){
           $flag1 = auth('api')->user()->userData()->increment('points', $refundedAmount);
           $flag2 = $userGift->delete();
           if($flag1 && $flag2){
               return true;
           }
           return false;
        });
        if($status){
            return response()->json(['success'=>'Gift refunded successfully']);
        }
        return response()->json(['error'=>'Something went wrong refunding your gift']);
    }
}
