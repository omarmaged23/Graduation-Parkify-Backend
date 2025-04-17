<?php

namespace App\Models;

use App\Services\MqttService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Public_Spot_Log extends Model
{
    use HasFactory;
    protected $guarded = [];
    public $timestamps = false;

    public function publicSpot()
    {
        return $this->belongsTo(Public_Spot::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected $casts = [
        'entered_at' => 'datetime', // Add this line
        'exited_at' => 'datetime',  // Optional, if you also want to cast this field
    ];
}
