<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ReservableSpotLogController extends Controller
{
    public function getReservableSpotLog(){
        return auth('api')->user()->reservableSpotLogs()
        ->limit(10)
        ->get()
        ->map(function ($spot) {
            return $spot->toArray() + ['spot_code' => $spot->reservableSpot->spot_code];
        });    
    }
}
