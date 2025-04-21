<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserDataController extends Controller
{
    public function setupUser(Request $request){
        $user = auth('api')->user();

        if ($user->userData && $user->userData->national && $user->userData->phone) {
            return response()->json(['error' => 'You already have national ID and phone attached to your account'], 422);
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
