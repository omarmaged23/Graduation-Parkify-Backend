<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Models\Location;
use App\Models\Public_Spot;
use App\Models\Public_Spot_Log;
use App\Models\Public_Spot_Used;
use App\Models\Reservable_Spot_Log;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private function checkLocation($location_id)
    {
       $location = Location::find($location_id);
       if(!$location){
           return false;
       }
       return $location;
    }
    ####################################################
    private function getTotalProfit($location_id)
    {
        if($location_id){
            $location = $this->checkLocation($location_id);
           if(!$location){
               return response()->json(['error' => 'location not found']);
           }
        }
        $guestsProfit = Billing::where('status','completed')->when($location_id, function ($query, $location) {
            return $query->where('location', $location);
        })->sum('amount');
        $usersPublicProfit = Public_Spot_Log::where('is_payed',1)->when($location_id, function ($query, $location_id) {
            return $query->where('location_id', $location_id);
        })->sum('invoice_price');
        $usersReservableProfit = Reservable_Spot_Log::where('is_payed',1)->when($location_id, function ($query, $location_id) {
            return $query->where('location_id', $location_id);
        })->sum('invoice_price');

        return response()->json(['success'=>$guestsProfit + $usersPublicProfit + $usersReservableProfit],200);
    }

    private function getAvailablePublicSpots($location_id){
        if($location_id){
            $location = $this->checkLocation($location_id);
            if(!$location){
                return response()->json(['error' => 'location not found']);
            }
        }
        $publicSpots = Public_Spot::where([['location_id',$location_id],['is_active',1]])->count();
        return response()->json(['success'=>$publicSpots],200);
    }

    private function getAvailableReservableSpots($location_id){
        if($location_id){
            $location = $this->checkLocation($location_id);
            if(!$location){
                return response()->json(['error' => 'location not found']);
            }
        }
        $reservableSpots = Public_Spot::where([['location_id',$location_id],['is_active',1]])->count();
        return response()->json(['success'=>$reservableSpots],200);
    }

    private function getTotalUsers(){
        $users = User::count();
        return response()->json(['success'=>$users],200);
    }
    ####################################################
    private function getPopularPublicSpots($location_id)
    {
        if($location_id){
            $location = $this->checkLocation($location_id);
            if(!$location){
                return response()->json(['error' => 'location not found']);
            }
        }
        $popularPublicSpots = Public_Spot_Used::when($location, function ($query, $location_id) {
            return $query->where('location_id', $location_id);
        })->select('spot_code', DB::raw('COUNT(*) as count'))
            ->groupBy('spot_code')
            ->orderByDesc('count')
            ->limit(5)
            ->get();
        return response()->json(['success'=>$popularPublicSpots],200);
    }

    private function getPopularReservableSpots($location_id)
    {
        if($location_id){
            $location = $this->checkLocation($location_id);
            if(!$location){
                return response()->json(['error' => 'location not found']);
            }
        }
        $popularReservableSpots = Reservable_Spot_Log::when($location, function ($query, $location) {
            $query->whereHas('reservableSpot', function ($q) use ($location) {
                $q->where('location', $location);
            });
        })->select('reservable_spot_id', DB::raw('COUNT(*) as count'))
            ->groupBy('reservable_spot_id')
            ->orderByDesc('count')
            ->limit(5)
            ->with('reservableSpot:spot_code,id') // eager load only spot_code + id
            ->get()
            ->map(function ($log) {
                return [
                    'spot_code' => $log->reservableSpot->spot_code ?? 'Unknown',
                    'count' => $log->count,
                ];
            });
        return response()->json(['success'=>$popularReservableSpots],200);
    }

}
