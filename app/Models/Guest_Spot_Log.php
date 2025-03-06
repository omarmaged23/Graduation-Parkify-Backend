<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guest_Spot_Log extends Model
{
    use HasFactory;
    protected $guarded=[];
    public $timestamps = false;

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }
    protected $casts = [
        'entered_at' => 'datetime', // Add this line
        'exited_at' => 'datetime',  // Optional, if you also want to cast this field
    ];
}
