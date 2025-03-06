<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservable_Spot_Log extends Model
{
    use HasFactory;
    protected $guarded= [];
    public $timestamps = false;
    public function reservableSpot()
    {
        return $this->belongsTo(Reservable_Spot::class, 'reservable_spot_id');
    }
    protected $casts = [
        'entered_at' => 'datetime', // Add this line
        'exited_at' => 'datetime',  // Optional, if you also want to cast this field
    ];
}
