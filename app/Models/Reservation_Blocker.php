<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation_Blocker extends Model
{
    use HasFactory;
    public function reservableSpot()
    {
        return $this->belongsTo(Reservable_Spot::class);
    }
}
