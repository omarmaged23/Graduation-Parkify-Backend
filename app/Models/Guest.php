<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    use HasFactory;
    public function guestSpotLogs()
    {
        return $this->hasMany(Guest_Spot_Log::class);
    }
}
