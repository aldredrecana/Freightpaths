<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A problem a driver reported from the road. Open until a dispatcher marks it resolved. */
class Incident extends Model
{
    public const TYPES = [
        'breakdown' => 'Breakdown',
        'flat_tire' => 'Flat tire',
        'accident' => 'Accident',
        'road_blocked' => 'Road blocked',
        'other' => 'Other problem',
    ];

    protected $fillable = [
        'schedule_id', 'driver_id', 'type', 'note', 'latitude', 'longitude', 'accuracy',
        'reported_at', 'resolved_at', 'resolved_by', 'resolution_note',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy' => 'float',
        'reported_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function getLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    // A link that opens the spot in Google Maps (works on phones and computers).
    public function getMapUrlAttribute(): ?string
    {
        return $this->latitude !== null
            ? 'https://www.google.com/maps?q='.$this->latitude.','.$this->longitude
            : null;
    }

    // What the app shows.
    public function toAppArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'label' => $this->label,
            'note' => $this->note,
            'reported_at' => $this->reported_at->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolution_note' => $this->resolution_note,
        ];
    }
}