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
            return array_merge(
                $spot->toArray(),
                [
                    'spot_code' => $spot->reservableSpot->spot_code,
                    'entered_at'  => $spot->entered_at?->format('F jS g:i:s A'),
                    'exited_at'   => $spot->exited_at?->format('F jS g:i:s A'),
                ]
            );
        });
    }
}
