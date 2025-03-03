<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;
    public function publicSpots()
    {
        return $this->hasMany(Public_Spot::class);
    }

    public function reservableSpots()
    {
        return $this->hasMany(Reservable_Spot::class);
    }
}
