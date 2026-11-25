<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Schedule extends Model
{
    protected $fillable = [
        'driver_id',
        'truck_id',
        'origin_address',
        'destination_address',
        'departure_at',
        'expected_arrival_at',
        'status',
        'assigned_at',
        'origin_lat',
        'origin_lng',
        'destination_lat',
        'destination_lng',
        'backload_of_id',
    ];

    protected $casts = [
        'departure_at' => 'datetime',
        'expected_arrival_at' => 'datetime',
        'assigned_at' => 'datetime',
        'origin_lat' => 'float',
        'origin_lng' => 'float',
        'destination_lat' => 'float',
        'destination_lng' => 'float',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public const ACTIVE = [
        'pending',
        'accepted',
        'en_route',
        'needs_reassignment',
    ];

    public const NEXT = [
        'pending' => ['accepted', 'cancelled'],
        'accepted' => ['en_route', 'cancelled'],
        'en_route' => ['delivered', 'cancelled'],
        'needs_reassignment' => ['cancelled'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public const FINISHED = ['delivered', 'cancelled'];

    public const DECLINE_REASONS = [
        'sick' => 'Sick',
        'emergency' => 'Emergency',
        'vehicle_problem' => 'Vehicle problem',
        'other' => 'Other',
    ];

    public function statusLogs(): HasMany
    {
        return $this->hasMany(DeliveryStatusLog::class)
            ->orderByDesc('logged_at')
            ->orderByDesc('id');
    }
        public function locationPings(): HasMany
    {
        return $this->hasMany(LocationPing::class);
    }

    public function restPeriods(): HasMany
    {
        return $this->hasMany(RestPeriod::class)->orderBy('started_at');
    }

    
    // Breaks the driver took with the "Take a break" button, oldest first.
    public function breaks(): HasMany
    {
        return $this->hasMany(DriverBreak::class)->orderBy('started_at');
    }

    // The break going on right now, if any.
    public function openBreak(): ?DriverBreak
    {
        return $this->breaks()->whereNull('ended_at')->reorder('started_at', 'desc')->first();
    }

    public function allowedNext(User $user): array
    {
        $next = self::NEXT[$this->status] ?? [];

        return array_values(
            array_filter($next, function ($status) use ($user) {
                return $status === 'cancelled'
                    ? $user->hasRole('admin') || $user->hasRole('dispatcher')
                    : $user->hasRole('driver');
            }),
        );
    }

        // Pickup and drop-off pins for the map: [lat, lng] or null when not set.
    public function routePlan(): array
    {
        return [
            'origin' => $this->origin_lat !== null ? [$this->origin_lat, $this->origin_lng] : null,
            'destination' => $this->destination_lat !== null ? [$this->destination_lat, $this->destination_lng] : null,
        ];
    }

    public function hasPins(): bool
    {
        return $this->origin_lat !== null || $this->destination_lat !== null;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, self::FINISHED, true);
    }

    public function getReferenceAttribute(): string
    {
        return 'FP-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    // 'urgent' or 'overdue' for trips that still need a driver to confirm.
    public function getUrgencyAttribute(): ?string
    {
        if (! in_array($this->status, ['pending', 'needs_reassignment'], true)) {
            return null;
        }

        if ($this->departure_at->isPast()) {
            return 'overdue';
        }

        $window = (int) config('freightpath.late_window_hours', 3);

        return $this->departure_at->lte(now()->addHours($window))
            ? 'urgent'
            : null;
    }

    // When the current driver was offered the trip (pending trips only).
    public function getAwaitingSinceAttribute(): ?Carbon
    {
        return $this->status === 'pending'
            ? $this->assigned_at ?? $this->created_at
            : null;
    }

    
    public function incidents()
    {
        return $this->hasMany(Incident::class)->latest('reported_at');
    }

    
    public function proof()
    {
        return $this->hasOne(DeliveryProof::class);
    }

    
    // The trip this backload follows (null for a normal trip).
    public function backloadOf()
    {
        return $this->belongsTo(Schedule::class, 'backload_of_id');
    }

    // Backloads offered after this trip.
    public function backloads()
    {
        return $this->hasMany(Schedule::class, 'backload_of_id')->orderBy('departure_at');
    }

    // A backload can be offered while the trip is accepted, on the way, or just delivered.
    public function canOfferBackload(): bool
    {
        return in_array($this->status, ['accepted', 'en_route', 'delivered'], true);
    }
}
