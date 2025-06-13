<?php

namespace App\Http\Controllers\Admin;

use App\Events\AdminActionPerformed;
use App\Exports\ParkingExport;
use App\Http\Controllers\Controller;
use App\Models\Dashboard; // Your new model
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
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

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

    /**
     * Get cached dashboard data or recalculate if needed
     */
    private function getCachedDashboardData($location_id = null)
    {
        if ($location_id) {
            $location_key = Location::find($location_id)->name;
        } else {
            $location_key = 'all';
        }

        // Check if cached data exists and is fresh (less than 12 hours old)
        $cached = Dashboard::where('location', $location_key)
            ->where('updated_at', '>', Carbon::now()->subHours(12))
            ->first();

        if ($cached) {
            return $cached;
        }

        // Recalculate and cache the data
        return $this->recalculateAndCache($location_id, $location_key);
    }

    /**
     * Recalculate all dashboard metrics and cache them
     */
    private function recalculateAndCache($location_id, $location_key)
    {
        $data = [
            'location' => $location_key,
            'total_profit' => $this->calculateTotalProfit($location_id),
            'available_public_spots' => $this->calculateAvailablePublicSpots($location_id),
            'available_reservable_spots' => $this->calculateAvailableReservableSpots($location_id),
            'total_users' => $this->calculateTotalUsers(),
            'popular_public_spots' => json_encode($this->calculatePopularPublicSpots($location_id)),
            'popular_reservable_spots' => json_encode($this->calculatePopularReservableSpots($location_id)),
            'active_user_accounts' => $this->calculateActiveUsers(),
            'inactive_user_accounts' => $this->calculateInactiveUsers(),
            'popular_gifts' => json_encode($this->calculatePopularGifts()),
            'monthly_profit' => json_encode($this->calculateMonthlyProfit($location_id),JSON_FORCE_OBJECT),
        ];

        // Update or create cache record
        Dashboard::updateOrCreate(
            ['location' => $location_key],
            $data
        );

        return Dashboard::where('location', $location_key)->first();
    }

    /**
     * Force refresh cache for a location
     */
    public function refreshDashboardCache(Request $request , $location_id = null)
    {
        if ($location_id) {
            $location_key = Location::find($location_id)->name;
        } else {
            $location_key = 'all';
        }

        if($location_id && !$this->checkLocation($location_id)){
            return response()->json(['error' => 'location not found']);
        }

        $this->recalculateAndCache($location_id, $location_key);

        return response()->json(['success' => 'Dashboard cache refreshed'], 200);
    }

    // Modified public methods to use cache
    public function getTotalProfit($location_id = null)
    {
        if($location_id && !$this->checkLocation($location_id)){
            return response()->json(['error' => 'location not found']);
        }

        $cached = $this->getCachedDashboardData($location_id);
        return response()->json(['success' => $cached->total_profit], 200);
    }

    public function getAvailablePublicSpots($location_id = null)
    {
        if($location_id && !$this->checkLocation($location_id)){
            return response()->json(['error' => 'location not found']);
        }

        $cached = $this->getCachedDashboardData($location_id);
        return response()->json(['success' => $cached->available_public_spots], 200);
    }

    public function getAvailableReservableSpots($location_id = null)
    {
        if($location_id && !$this->checkLocation($location_id)){
            return response()->json(['error' => 'location not found']);
        }

        $cached = $this->getCachedDashboardData($location_id);
        return response()->json(['success' => $cached->available_reservable_spots], 200);
    }

    public function getTotalUsers()
    {
        $cached = $this->getCachedDashboardData();
        return response()->json(['success' => $cached->total_users], 200);
    }

    public function getPopularPublicSpots($location_id = null)
    {
        if($location_id && !$this->checkLocation($location_id)){
            return response()->json(['error' => 'location not found']);
        }

        $cached = $this->getCachedDashboardData($location_id);
        $spots = json_decode($cached->popular_public_spots);

        return response()->json(['success' => $spots], 200);
    }

    public function getPopularReservableSpots($location_id = null)
    {
        if($location_id && !$this->checkLocation($location_id)){
            return response()->json(['error' => 'location not found']);
        }

        $cached = $this->getCachedDashboardData($location_id);
        $spots = json_decode($cached->popular_reservable_spots);
        return response()->json(['success' => $spots], 200);
    }

    public function getUserAccountStatus()
    {
        $cached = $this->getCachedDashboardData();
        return response()->json(['success' => [
            'active' => $cached->active_user_accounts,
            'inactive' => $cached->inactive_user_accounts
        ]], 200);
    }

    public function getPopularGifts()
    {
        $cached = $this->getCachedDashboardData();
        $gift = json_decode($cached->popular_gifts);
        return response()->json(['success' => $gift], 200);
    }

    public function getMonthlyProfit($location_id = null)
    {
        if($location_id && !$this->checkLocation($location_id)){
            return response()->json(['error' => 'location not found']);
        }

        $cached = $this->getCachedDashboardData($location_id);
        $profit = json_decode($cached->monthly_profit,true);
        $monthOrder = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $orderedProfit = [];
        foreach ($monthOrder as $month) {
            $key = $month;
            if (array_key_exists($key, $profit)) {
                $orderedProfit[$key] = $profit[$key];
            }
        }
        return response()->json(['success' => $orderedProfit], 200);    }

    /**
     * Get all dashboard data at once (useful for reports)
     */
    public function getAllDashboardData($location_id = null)
    {
        $auth = auth()->user();
        if($location_id && !$this->checkLocation($location_id)){
            return response()->json(['error' => 'location not found']);
        }

        $cached = $this->getCachedDashboardData($location_id);
        $data = [
            'location' => $cached->location,
            'total_profit' => $cached->total_profit,
            'available_public_spots' => $cached->available_public_spots,
            'available_reservable_spots' => $cached->available_reservable_spots,
            'total_users' => $cached->total_users,
            'popular_public_spots' => json_decode($cached->popular_public_spots,true),
            'popular_reservable_spots' => json_decode($cached->popular_reservable_spots,true),
            'active_user_accounts' => $cached->active_user_accounts,
            'inactive_user_accounts' => $cached->inactive_user_accounts,
            'popular_gifts' => json_decode($cached->popular_gifts,true),
            'monthly_profit' => json_decode($cached->monthly_profit,true),
            'last_updated' => $cached->updated_at->format('Y-m-d H:i:s')
        ];
        $filename = 'reports/parking_data_'. $cached->location .' '. now()->timestamp . '.xlsx';
        Excel::store(new ParkingExport([$data]), $filename, 'filebase');

        // Generate a temporary (60-minute) signed download URL
        $downloadUrl = Storage::disk('filebase')->temporaryUrl(
            $filename,
            now()->addMinutes(60)
        );
        event(new AdminActionPerformed($auth->name,$auth->email,"Generated $cached->location Report",$auth->role));

        return response()->json([
            'success' => $downloadUrl
        ], 200);
    }

    public function getAllLocationsReport(){
        $auth = auth('admin')->user();
        $locations = Location::pluck('id');
        $cached = $this->getCachedDashboardData();
        $data = [[
            'location' => $cached->location,
            'total_profit' => $cached->total_profit,
            'available_public_spots' => $cached->available_public_spots,
            'available_reservable_spots' => $cached->available_reservable_spots,
            'total_users' => $cached->total_users,
            'popular_public_spots' => json_decode($cached->popular_public_spots,true),
            'popular_reservable_spots' => json_decode($cached->popular_reservable_spots,true),
            'active_user_accounts' => $cached->active_user_accounts,
            'inactive_user_accounts' => $cached->inactive_user_accounts,
            'popular_gifts' => json_decode($cached->popular_gifts,true),
            'monthly_profit' => json_decode($cached->monthly_profit,true),
            'last_updated' => $cached->updated_at->format('Y-m-d H:i:s')
        ]];
        foreach ($locations as $location_id){
            $cached = $this->getCachedDashboardData($location_id);
            $data[] = [
                'location' => $cached->location,
                'total_profit' => $cached->total_profit,
                'available_public_spots' => $cached->available_public_spots,
                'available_reservable_spots' => $cached->available_reservable_spots,
                'total_users' => $cached->total_users,
                'popular_public_spots' => json_decode($cached->popular_public_spots,true),
                'popular_reservable_spots' => json_decode($cached->popular_reservable_spots,true),
                'active_user_accounts' => $cached->active_user_accounts,
                'inactive_user_accounts' => $cached->inactive_user_accounts,
                'popular_gifts' => json_decode($cached->popular_gifts,true),
                'monthly_profit' => json_decode($cached->monthly_profit,true),
                'last_updated' => $cached->updated_at->format('Y-m-d H:i:s')
            ];
        }
        $filename = 'reports/parking_data_'. 'collection' .' '. now()->timestamp . '.xlsx';
        Excel::store(new ParkingExport($data), $filename, 'filebase');

        // Generate a temporary (60-minute) signed download URL
        $downloadUrl = Storage::disk('filebase')->temporaryUrl(
            $filename,
            now()->addMinutes(60)
        );
        event(new AdminActionPerformed($auth->name,$auth->email,"Generated System Report",$auth->role));

        return response()->json([
            'success' => $downloadUrl
        ], 200);
    }

    // Private calculation methods (your original logic)
    private function calculateTotalProfit($location_id = null)
    {
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

        return round($guestsProfit + $usersPublicProfit + $usersReservableProfit, 2);
    }

    private function calculateAvailablePublicSpots($location_id = null)
    {
        return Public_Spot::where('is_active',1)->when($location_id, function ($query, $location_id) {
            return $query->where('location_id', $location_id);
        })->count();
    }

    private function calculateAvailableReservableSpots($location_id = null)
    {
        return Reservable_Spot::where('is_active',1)->when($location_id, function ($query, $location_id) {
            return $query->where('location_id', $location_id);
        })->count();
    }

    private function calculateTotalUsers()
    {
        return User::count();
    }

    private function calculateTopSpotsWithOthers($query, $groupColumn, $displayCallback, $limit = 4)
    {
        $grouped = $query
            ->select($groupColumn, DB::raw('COUNT(*) as count'))
            ->groupBy($groupColumn)
            ->orderByDesc('count')
            ->get();

        $total = $grouped->sum('count');

        $topItems = $grouped->take($limit);
        $result = [];

        $usedPercentage = 0;
        foreach ($topItems as $item) {
            $percentage = $total > 0 ? round(($item->count / $total) * 100, 2) : 0;
            $usedPercentage += $percentage;

            $result[] = [
                'spot_code' => $displayCallback($item),
                'percentage' => $percentage,
            ];
        }
        $othersPercentage = max(0, round(100 - $usedPercentage, 2));

        $result[] = [
            'spot_code' => 'Others',
            'percentage' => $othersPercentage,
        ];

        return $result;
    }

    private function calculatePopularPublicSpots($location_id = null)
    {
        $query = Public_Spot_Used::query();

        if ($location_id) {
            $query->where('location_id', $location_id);
        }

        return $this->calculateTopSpotsWithOthers($query, 'spot_code', fn($item) => $item->spot_code);
    }

    private function calculatePopularReservableSpots($location_id = null)
    {
        $query = Reservable_Spot_Log::with('reservableSpot:id,spot_code');

        if ($location_id) {
            $query->whereHas('reservableSpot', fn($q) => $q->where('location_id', $location_id));
        }

        return $this->calculateTopSpotsWithOthers($query, 'reservable_spot_id', function ($item) {
            return $item->reservableSpot->spot_code ?? 'Unknown';
        });
    }

    private function calculateActiveUsers()
    {
        $total = User_Data::count();
        $active = User_Data::where('is_active', 1)->count();

        return $total > 0 ? round(($active / $total) * 100, 2) : 0;
    }

    private function calculateInactiveUsers()
    {
        $total = User_Data::count();
        $inactive = User_Data::where('is_active', 0)->count();

        return $total > 0 ? round(($inactive / $total) * 100, 2) : 0;
    }

    private function calculatePopularGifts()
    {
        return User_Gift::select('gift_id', DB::raw('COUNT(*) as usage_count'))
            ->groupBy('gift_id')
            ->orderByDesc('usage_count')
            ->limit(3)
            ->with('gift:id,description,discount_percentage,cost')
            ->get()
            ->map(function ($item) {
                return [
                    'description' => $item->gift->description ?? 'Unknown',
                    'discount' => $item->gift->discount_percentage ?? 0,
                    'cost' => $item->gift->cost ?? 'Unknown',
                    'usage_count' => $item->usage_count,
                ];
            })
            ->toArray();
    }

    private function calculateMonthlyProfit($location_id = null)
    {
        $months = collect(range(1, 12))->mapWithKeys(fn($m) => [
            Carbon::create()->month($m)->format('M') => 0
        ]);

        $getMonthlySums = function ($model, $dateColumn, $sumColumn, $filters = []) use ($location_id) {
            $query = $model::select(
                DB::raw("MONTH($dateColumn) as month"),
                DB::raw("SUM($sumColumn) as total")
            );

            if ($model === \App\Models\Reservable_Spot_Log::class && $location_id) {
                $query->whereHas('reservableSpot', function ($q) use ($location_id) {
                    $q->where('location_id', $location_id);
                });
            } elseif ($location_id && !empty($filters['location_column'])) {
                $query->where($filters['location_column'], $location_id);
            }

            if (!empty($filters['extra'])) {
                foreach ($filters['extra'] as $col => $val) {
                    $query->where($col, $val);
                }
            }

            return $query->groupBy(DB::raw("MONTH($dateColumn)"))
                ->pluck('total', 'month')
                ->mapWithKeys(fn($value, $monthNum) => [
                    Carbon::create()->month($monthNum)->format('M') => round($value, 2)
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
        round(($guests[$month] ?? 0) + ($public[$month] ?? 0) + ($reservable[$month] ?? 0), 2)
        );

        // Return as array to maintain order
        return $totalMonthly->toArray();
    }
}
