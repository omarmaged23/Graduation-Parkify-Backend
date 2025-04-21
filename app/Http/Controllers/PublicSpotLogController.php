<?php

namespace App\Http\Controllers;

use App\Models\Public_Spot_Log;
use Illuminate\Http\Request;

class PublicSpotLogController extends Controller
{
    public function getPublicSpotLog()
    {
        return auth('api')->user()->publicSpotLogs()->limit(10)->get();
    }
}
