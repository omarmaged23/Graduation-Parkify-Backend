<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservable_Spot extends Model
{
    use HasFactory;
    public function location()
    {
        return $this->belongsTo(Location::class);
    }
    public function spotManagement()
    {
        return $this->belongsTo(Spot_Management::class, 'management_id'); // Custom FK
    }
    public function reservableSpotLog()
    {
        return $this->belongsTo(Reservable_Spot_Log::class);
    }
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
}
