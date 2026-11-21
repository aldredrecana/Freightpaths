<?php

namespace App\Services;

use App\Models\DeliveryStatusLog;
use App\Models\LocationPing;
use App\Models\Schedule;
use Illuminate\Support\Collection;

/** Things the dispatcher should act on now: declined trips, phones with no signal, trips about to leave unaccepted. */
class StaffAlerts
{
    // Every alert, most important first.
    public function all(): Collection
    {
        return $this->declined()
            ->concat($this->noSignal())
            ->concat($this->urgent())
            ->values();
    }

    // A driver declined, so the trip needs a new driver.
    public function declined(): Collection
    {
        return Schedule::with('driver.user')
            ->where('status', 'needs_reassignment')
            ->addSelect(['schedules.*', 'declined_at' => $this->lastLog('needs_reassignment', 'logged_at')])
            ->addSelect(['decline_reason' => $this->lastLog('needs_reassignment', 'remarks')])
            ->withCasts(['declined_at' => 'datetime'])
            ->get()
            ->map(fn (Schedule $s) => $this->item(
                'declined',
                "{$s->reference}: driver declined",
                $s->driver->user->name.' declined'.($s->decline_reason ? " ({$s->decline_reason})" : '').'. Assign another driver.',
                $s,
                $s->declined_at,
            ));
    }

    // On the road, but the phone has sent no GPS point for longer than the allowed gap.
    public function noSignal(): Collection
    {
        $gap = (int) config('freightpath.rest_max_gap_minutes', 10);

        return Schedule::with('driver.user')
            ->where('status', 'en_route')
            ->addSelect(['schedules.*', 'last_ping_at' => LocationPing::select('recorded_at')
                ->whereColumn('schedule_id', 'schedules.id')
                ->latest('recorded_at')
                ->limit(1)])
            ->addSelect(['started_at' => $this->firstLog('en_route', 'logged_at')])
            ->withCasts(['last_ping_at' => 'datetime', 'started_at' => 'datetime'])
            ->get()
            ->filter(function (Schedule $s) use ($gap) {
                $since = $s->last_ping_at ?? $s->started_at;

                return $since && $since->lt(now()->subMinutes($gap));
            })
            ->map(function (Schedule $s) {
                $since = $s->last_ping_at ?? $s->started_at;
                $what = $s->last_ping_at ? 'No GPS since '.$since->format('g:i A') : 'No GPS received since the trip started';

                return $this->item(
                    'no_signal',
                    "{$s->reference}: no signal",
                    "{$what}. Call {$s->driver->user->name} ({$s->driver->contact_no}).",
                    $s,
                    $since,
                );
            })
            ->values();
    }

    // Still not accepted, and departure is close (or already passed).
    public function urgent(): Collection
    {
        $window = (int) config('freightpath.late_window_hours', 3);

        return Schedule::with('driver.user')
            ->where('status', 'pending')
            ->where('departure_at', '<=', now()->addHours($window))
            ->orderBy('departure_at')
            ->get()
            ->map(fn (Schedule $s) => $this->item(
                'urgent',
                "{$s->reference}: not accepted yet",
                ($s->departure_at->isPast() ? 'Was due to leave ' : 'Leaves ').$s->departure_at->diffForHumans()
                    .'. Waiting for '.$s->driver->user->name.'.',
                $s,
                $s->departure_at,
            ));
    }

    // The newest timeline entry of one status for each trip (used inside the queries above).
    private function lastLog(string $status, string $column)
    {
        return DeliveryStatusLog::select($column)
            ->whereColumn('schedule_id', 'schedules.id')
            ->where('status', $status)
            ->orderByDesc('logged_at')
            ->orderByDesc('id') // two entries in the same second: take the one saved last
            ->limit(1);
    }

    // The first timeline entry of one status (for example, when the trip started).
    private function firstLog(string $status, string $column)
    {
        return DeliveryStatusLog::select($column)
            ->whereColumn('schedule_id', 'schedules.id')
            ->where('status', $status)
            ->orderBy('logged_at')
            ->orderBy('id')
            ->limit(1);
    }

    private function item(string $type, string $title, string $detail, Schedule $s, $at): array
    {
        return [
            'key' => $type.'-'.$s->id,
            'type' => $type,
            'title' => $title,
            'detail' => $detail,
            'url' => route('schedules.show', $s),
            'ago' => $at ? $at->diffForHumans() : '',
        ];
    }
}