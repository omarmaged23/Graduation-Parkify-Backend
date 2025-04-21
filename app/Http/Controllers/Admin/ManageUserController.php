<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guest_Spot_Log;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManageUserController extends Controller
{
    public function getAllUsers()
    {
        // Add to api resource to only return name,phone and national id
        $users = User::with('userData')->get();
        if(!$users){
            return response()->json(['error'=>"No users found."],422);
        }
        return response()->json(['success'=>$users],200);
    }
    public function getGuestLogs(){
        $logs = Guest_Spot_Log::all();
        if(!$logs){
            return response()->json(['error'=>'No logs found']);
        }
        return response()->json(['success'=>$logs]);
    }
    public function getUserWithLogs($id)
    {
        $user = User::with(['userData' => function($query) {
            $query->select([
                'user_id',
                'national',
                'phone',
                'address',
                'is_active',
                'balance',
                'points',
            ]);
        }])
            ->select([
                'id',
                'name',
                'email',
            ])
            ->find($id);

        if (!$user) {
            return response()->json(['error' => "User not found."], 422);
        }

        // Get combined recent logs (last 10 from both tables)
        $recentLogs = DB::query()
            ->fromSub($user->publicSpotLogs()
            ->select(
                'public__spot__logs.license_plate',
                'public__spot__logs.invoice_price',
                'public__spot__logs.is_payed',
                'public__spot__logs.entered_at',
                'public__spot__logs.exited_at',
                DB::raw('NULL as spot'),
                DB::raw("'public' as log_type")
            )
            ->unionAll(
                $user->reservableSpotLogs()
                    ->select(
                        'reservable__spot__logs.license_plate',
                        'reservable__spot__logs.invoice_price',
                        'reservable__spot__logs.is_payed',
                        'reservable__spot__logs.entered_at',
                        'reservable__spot__logs.exited_at',
                        DB::raw('reservable__spots.spot_code as spot'), // Join reservable spot name
                        DB::raw("'private' as log_type")
                    )
                    ->leftJoin('reservable__spots', 'reservable__spot__logs.reservable_spot_id', '=', 'reservable__spots.id')
            ),
            'combined_logs'
        )
        ->orderBy('entered_at', 'desc')
        ->take(10)
        ->get();

        // if request is from user token just get logs
        $isUser = auth('api')->check();
        if($isUser){
            return response()->json([
                'success' => $recentLogs
            ], 200);
        }
        $userData = $user->toArray();
        $userData['recent_logs'] = $recentLogs;

        return response()->json([
            'success' => $userData
        ], 200);

    }

    public function changeUserStatus(Request $request,$id)
    {
        $request->validate([
            'status'=>['required','in:0,1']
        ]);
        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => "User not found."], 422);
        }
        $status = $user->userData()->update(['is_active'=>$request->status]);
        if (!$status) {
            return response()->json(['error' => "Something went while updating user status wrong."], 422);
        }
        return response()->json(['success' => "User status updated successfully."], 200);
    }

}
