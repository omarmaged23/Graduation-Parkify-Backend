<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Spot_Management extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function publicSpots()
    {
        return $this->hasMany(Public_Spot::class, 'management_id');
    }

    public function reservableSpots()
    {
        return $this->hasMany(Reservable_Spot::class, 'management_id');
    }
}
