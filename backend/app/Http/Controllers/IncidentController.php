<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use Illuminate\Http\Request;

/** Problem reports from drivers: the dispatcher sees them and marks them resolved. */
class IncidentController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status', 'open');
        if (! in_array($status, ['open', 'resolved', 'all'], true)) {
            $status = 'open';
        }

        $incidents = Incident::with(['driver.user', 'schedule.truck', 'resolver'])
            ->when($status === 'open', fn ($query) => $query->whereNull('resolved_at'))
            ->when($status === 'resolved', fn ($query) => $query->whereNotNull('resolved_at'))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($w) use ($like) {
                    $w->where('note', 'ilike', $like)
                        ->orWhereHas('driver.user', fn ($u) => $u->where('name', 'ilike', $like))
                        ->orWhereHas('schedule.truck', fn ($t) => $t->where('plate_no', 'ilike', $like));
                });
            })
            ->orderByRaw('resolved_at IS NOT NULL') // open ones first
            ->latest('reported_at')
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'open' => Incident::open()->count(),
            'resolved' => Incident::whereNotNull('resolved_at')->count(),
        ];

        return view('incidents.index', compact('incidents', 'q', 'status', 'counts'));
    }

    public function resolve(Request $request, Incident $incident)
    {
        $data = $request->validate([
            'resolution_note' => ['nullable', 'string', 'max:300'],
        ]);

        if ($incident->resolved_at === null) {
            $incident->update([
                'resolved_at' => now(),
                'resolved_by' => $request->user()->id,
                'resolution_note' => $data['resolution_note'] ?? null,
            ]);
        }

        return back()->with('status', 'Problem report marked as resolved.');
    }
}