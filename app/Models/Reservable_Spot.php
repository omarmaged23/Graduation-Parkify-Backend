<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservable_Spot extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function location()
    {
        return $this->belongsTo(Location::class);
    }
    public function spotManagement()
    {
        return $this->belongsTo(Spot_Management::class, 'management_id'); // Custom FK
    }

    public function reservableSpotLogs()
    {
        return $this->hasMany(Reservable_Spot_Log::class, 'reservable_spot_id');
    }
    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'reservable_spot_id');
    }
}
