<?php

namespace App\Models;

use App\Services\MqttService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Public_Spot_Log extends Model
{
    use HasFactory;
    protected $guarded = [];
    public $timestamps = false;

//    protected static function boot()
//    {
//        parent::boot();
//
//        static::created(function ($log) {
//            self::syncToMqttTable($log);
//        });
//
//        static::updated(function ($log) {
//            self::syncToMqttTable($log);
//        });
//    }
//
//    private static function syncToMqttTable($log)
//    {
//        // Get location based on license plate (you may need a relationship for this)
//        $location = 'default_location'; // You can replace this with an actual lookup
//
//        DB::table('mqtt_spot_logs')->updateOrInsert(
//            ['license_plate' => $log->license_plate],
//            [
//                'location' => $location,
//                'entered_at' => $log->entered_at,
//                'exited_at' => $log->exited_at,
//            ]
//        );
//
//        MqttService::publish("parking/spots", [
//            'license_plate' => $log->license_plate,
//            'location' => $location,
//            'entered_at' => $log->entered_at,
//            'exited_at' => $log->exited_at,
//        ]);
//    }

    public function publicSpot()
    {
        return $this->belongsTo(Public_Spot::class);
    }
    protected $casts = [
        'entered_at' => 'datetime', // Add this line
        'exited_at' => 'datetime',  // Optional, if you also want to cast this field
    ];
}
