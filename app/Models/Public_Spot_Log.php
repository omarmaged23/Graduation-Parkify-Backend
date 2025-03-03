<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Public_Spot_Log extends Model
{
    use HasFactory;
    public function publicSpots()
    {
        return $this->hasMany(Public_Spot::class);
    }
}
