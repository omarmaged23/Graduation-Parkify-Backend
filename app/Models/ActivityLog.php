<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;
    protected $table = 'admin_activity_log';
    protected $guarded = [];
    protected $casts = [
        'created_at' => 'datetime:F jS g:i:s A',
    ];
}
