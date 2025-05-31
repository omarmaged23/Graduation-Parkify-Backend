<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mqtt_Spot_Log extends Model
{
    use HasFactory;
    protected $table = 'mqtt_spot_logs';
    protected $guarded = [];
    public function scopeLocationCount($query, $type ,$location)
    {
        return $query->where([['type','=',$type],['location','=',$location]])->count();
    }
}
