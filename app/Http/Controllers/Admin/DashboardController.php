<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guest_Spot_Log;
use App\Models\Location;
use App\Models\Public_Spot;
use App\Models\Public_Spot_Log;
use App\Models\Public_Spot_Used;
use App\Models\Reservable_Spot;
use App\Models\Reservable_Spot_Log;
use App\Models\User;
use App\Models\User_Data;
use App\Models\User_Gift;
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
    public function getTotalProfit($location_id = null)
    {
        if($location_id){
            $location = $this->checkLocation($location_id);
           if(!$location){
               return response()->json(['error' => 'location not found']);
           }
        }
        $guestsProfit = Guest_Spot_Log::where('is_payed',1)->when($location_id, function ($query, $location_id) {
            return $query->where('location_id', $location_id);
        })->sum('invoice_price');
        $usersPublicProfit = Public_Spot_Log::where('is_payed',1)->when($location_id, function ($query, $location_id) {
            return $query->where('location_id', $location_id);
        })->sum('invoice_price');
        $usersReservableProfit = Reservable_Spot_Log::where('is_payed',1)->when($location_id, function ($query, $location_id) {
            return $query->whereHas('reservableSpot', function ($q) use ($location_id) {
                $q->where('location_id', $location_id);
            });
        })->sum('invoice_price');

        return response()->json(['success'=>round($guestsProfit + $usersPublicProfit + $usersReservableProfit,2)],200);
    }

    public function getAvailablePublicSpots($location_id = null){
        if($location_id){
            $location = $this->checkLocation($location_id);
            if(!$location){
                return response()->json(['error' => 'location not found']);
            }
        }
        $publicSpots = Public_Spot::where('is_active',1)->when($location_id, function ($query, $location_id) {
            return $query->where('location_id', $location_id);
        })->count();
        return response()->json(['success'=>$publicSpots],200);
    }

    public function getAvailableReservableSpots($location_id = null){
        if($location_id){
            $location = $this->checkLocation($location_id);
            if(!$location){
                return response()->json(['error' => 'location not found']);
            }
        }
        $reservableSpots = Reservable_Spot::where('is_active',1)->when($location_id, function ($query, $location_id) {
            return $query->where('location_id', $location_id);
        })->count();
        return response()->json(['success'=>$reservableSpots],200);
    }

    public function getTotalUsers(){
        $users = User::count();
        return response()->json(['success'=>$users],200);
    }
    ####################################################
    public function getPopularPublicSpots($location_id = null)
    {
        if($location_id){
            $location = $this->checkLocation($location_id);
            if(!$location){
                return response()->json(['error' => 'location not found']);
            }
        }
        $popularPublicSpots = Public_Spot_Used::when($location_id, function ($query, $location_id) {
            return $query->where('location_id', $location_id);
        })->select('spot_code', DB::raw('COUNT(*) as count'))
            ->groupBy('spot_code')
            ->orderByDesc('count')
            ->limit(5)
            ->get();
        return response()->json(['success'=>$popularPublicSpots],200);
    }

    public function getPopularReservableSpots($location_id = null)
    {
        if($location_id){
            $location = $this->checkLocation($location_id);
            if(!$location){
                return response()->json(['error' => 'location not found']);
            }
        }
        $popularReservableSpots = Reservable_Spot_Log::when($location_id, function ($query, $location_id) {
            $query->whereHas('reservableSpot', function ($q) use ($location_id) {
                $q->where('location_id', $location_id);
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

    public function getUserAccountStatus()
    {
        $counts = User_Data::select('is_active', DB::raw('COUNT(*) as total'))
            ->groupBy('is_active')
            ->get()
            ->mapWithKeys(function ($item) {
                return [
                    $item->is_active ? 'active' : 'inactive' => $item->total
                ];
            });
        return response()->json(['success'=>$counts],200);
    }

    public function getPopularGifts()
    {
        $topGifts = User_Gift::select('gift_id', DB::raw('COUNT(*) as usage_count'))
            ->groupBy('gift_id')
            ->orderByDesc('usage_count')
            ->limit(3)
            ->with('gift:id,description,discount_percentage,cost') // only fetch required fields
            ->get()
            ->map(function ($item) {
                return [
                    'description' => $item->gift->description ?? 'Unknown',
                    'discount' => $item->gift->discount_percentage ?? 0,
                    'cost' => $item->gift->cost ?? 'Unknown',
                    'usage_count' => $item->usage_count,
                ];
            });

        return response()->json(['success'=>$topGifts],200);
    }

    public function getMonthlyProfit($location_id = null)
    {
        if($location_id){
            $location = $this->checkLocation($location_id);
            if(!$location){
                return response()->json(['error' => 'location not found']);
            }
        }
        $months = collect(range(1, 12))->mapWithKeys(fn($m) => [
            \Carbon\Carbon::create()->month($m)->format('M') => 0
        ]);

        $getMonthlySums = function ($model, $dateColumn, $sumColumn, $filters = []) use ($location_id) {
            $query = $model::select(
                DB::raw("MONTH($dateColumn) as month"),
                DB::raw("SUM($sumColumn) as total")
            );

            // Use whereHas for Reservable_Spot_Log relation filter
            if ($model === \App\Models\Reservable_Spot_Log::class && $location_id) {
                $query->whereHas('reservableSpot', function ($q) use ($location_id, $filters) {
                    $q->where('location_id', $location_id);
                });
            } elseif ($location_id) {
                $query->where($filters['location_column'], $location_id);
            }

            // Apply other optional filters
            if (!empty($filters['extra'])) {
                foreach ($filters['extra'] as $col => $val) {
                    $query->where($col, $val);
                }
            }

            return $query->groupBy(DB::raw("MONTH($dateColumn)"))
                ->pluck('total', 'month')
                ->mapWithKeys(fn($value, $monthNum) => [
                    \Carbon\Carbon::create()->month($monthNum)->format('M') => round($value,2)
                ]);
        };

        $guests = $months->merge(
            $getMonthlySums(Guest_Spot_Log::class, 'entered_at', 'invoice_price', [
                'location_column' => 'location_id',
                'extra' => ['is_payed' => 1]
            ])
        );

        $public = $months->merge(
            $getMonthlySums(Public_Spot_Log::class, 'entered_at', 'invoice_price', [
                'location_column' => 'location_id',
                'extra' => ['is_payed' => 1]
            ])
        );

        $reservable = $months->merge(
            $getMonthlySums(Reservable_Spot_Log::class, 'entered_at', 'invoice_price', [
                'location_column' => null,
                'extra' => ['is_payed' => 1]
            ])
        );

        $totalMonthly = $months->map(fn($_, $month) =>
            ($guests[$month] ?? 0) + ($public[$month] ?? 0) + ($reservable[$month] ?? 0)
        );

        return response()->json(['success' => $totalMonthly], 200);
    }
}
