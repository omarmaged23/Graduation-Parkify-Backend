<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserDataController extends Controller
{
    public function setupUser(Request $request){
        $user = $request->user();

        if ($user->userData && $user->userData->national && $user->userData->phone) {
            return response()->json(['msg' => 'You already have national ID and phone attached to your account'], 409);
        }
        $request->validate([
            'national' => ['required', 'string', 'unique:user__data,national' ,'size:14', 'regex:/^\d{14}$/'],
            'phone' => ['required', 'string', 'unique:user__data,phone' ,'size:11', 'regex:/^\d{11}$/'],
            'plate' => ['required', 'string', 'unique:license__plates,plate' ,'min:2']
        ]);

        $user->userData()->create([
            'national' => $request->national,
            'phone' => $request->phone
        ]);

        $plates = $user->licensePlates()->create([
            'plate' => $request->plate
        ]);
        return $plates;
    }
}
