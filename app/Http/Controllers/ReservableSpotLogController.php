<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ReservableSpotLogController extends Controller
{
    public function getReservableSpotLog(){
        return auth()->user()->reservableSpotLogs()->Limit(10)->get();
    }
}
