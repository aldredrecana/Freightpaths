<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestPeriod extends Model
{
    protected $fillable = [
        'schedule_id', 'started_at', 'ended_at', 'duration_seconds',
        'latitude', 'longitude', 'ping_count',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];
}