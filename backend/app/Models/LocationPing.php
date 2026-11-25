<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocationPing extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'schedule_id', 'driver_id', 'latitude', 'longitude',
        'accuracy', 'speed', 'recorded_at', 'activity', 'activity_confidence',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'recorded_at' => 'datetime',
    ];

    /**
     * The driver walking with the phone, not the truck moving: the phone's Activity
     * Recognition says walking (or running) AND the GPS speed is low. Fast GPS always
     * means the truck is moving, whatever the activity says.
     */
    public function isWalking(): bool
    {
        return in_array($this->activity, ['walking', 'running'], true)
            && ($this->speed === null || $this->speed < 0 || $this->speed <= config('freightpath.walk_max_speed_ms', 2.8));
    }
}