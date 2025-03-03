<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function reservableSpot()
    {
        return $this->belongsTo(Reservable_Spot::class);
    }
    public function reservationBlocker()
    {
        return $this->hasOne(Reservation_Blocker::class);
    }
}
