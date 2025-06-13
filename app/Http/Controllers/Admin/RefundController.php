<?php

namespace App\Http\Controllers\Admin;

use App\Events\AdminActionPerformed;
use App\Http\Controllers\Controller;
use App\Models\Refund;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function getRefundPercentage()
    {
        $refund = Refund::first();
        if(!$refund){
            return response()->json(['success' => 100]);
        }
        return response()->json(['success' => $refund->percentage]);
    }
    public function editRefundPercentage(Request $request)
    {
        $request->validate([
            'percentage' => ['required','numeric','regex:/^\d+(\.\d{1,2})?$/']
        ]);
        $status = Refund::updateOrCreate([],[
            'percentage' => $request->percentage
        ]);
        if(!$status){
            return response()->json(['error' => 'Something went wrong while updating your refund percentage.']);
        }
        $auth = auth('admin')->user();
        event(new AdminActionPerformed($auth->name,$auth->email,"Changed refund percentage to $status->percentage",$auth->role));
        return response()->json(['success' => 'Successfully updated your refund percentage.']);
    }
}
