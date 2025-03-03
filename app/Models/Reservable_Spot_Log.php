<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservable_Spot_Log extends Model
{
    use HasFactory;
    public function reservableSpots()
    {
        return $this->hasMany(Reservable_Spot::class);
    }
}
