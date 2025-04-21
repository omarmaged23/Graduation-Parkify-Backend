<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gift extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function userGifts()
    {
        return $this->hasMany(User_Gift::class);
    }
}
