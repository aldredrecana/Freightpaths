<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The photo and signature taken when a trip is delivered. */
class DeliveryProof extends Model
{
    protected $fillable = [
        'schedule_id', 'photo', 'signature', 'received_by', 'note', 'latitude', 'longitude', 'captured_at',
    ];

    // The pictures are big: never include them by accident in JSON.
    protected $hidden = ['photo', 'signature'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'captured_at' => 'datetime',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function getMapUrlAttribute(): ?string
    {
        return $this->latitude !== null
            ? 'https://www.google.com/maps?q='.$this->latitude.','.$this->longitude
            : null;
    }
}