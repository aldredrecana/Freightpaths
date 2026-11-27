<?php

namespace App\Http\Controllers;

use App\Models\DeliveryStatusLog;
use App\Models\Schedule;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    private const FINISHED = ['delivered', 'cancelled'];

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        $trips = $this->filtered($q, $status)
            ->paginate(8)
            ->withQueryString();

        $selected = null;
        $tripId = (int) $request->query('trip', 0);

        if ($tripId > 0) {
            $selected = Schedule::whereIn('status', self::FINISHED)->find(
                $tripId,
            );
        }

        $selected ??= $trips->first();
        $selected?->load(['driver.user', 'truck', 'statusLogs.loggedBy']);

        return view(
            'transactions.index',
            compact('trips', 'selected', 'q', 'status'),
        );
    }

    // Downloads the finished trips as a CSV file that opens in Excel.
    // Uses the same search and status filter as the page.
    public function export(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        $trips = $this->filtered($q, $status)
            ->select('schedules.*')
            // Finish time = the last timeline entry (same as the page shows).
            ->addSelect(['finished_at' => DeliveryStatusLog::select('logged_at')
                ->whereColumn('schedule_id', 'schedules.id')
                ->latest('logged_at')
                ->limit(1)])
            ->withCasts(['finished_at' => 'datetime'])
            ->withCount('restPeriods')
            ->withSum('restPeriods', 'duration_seconds')
            ->get();

        $filename = 'freightpath-trip-history-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($trips) {
            $out = fopen('php://output', 'w');

            // Tells Excel the file is UTF-8 (so names like "Señor" show correctly).
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Reference', 'Status', 'Driver', 'Truck', 'Origin', 'Destination',
                'Departure', 'Expected arrival', 'Finished', 'Rest stops', 'Total rest (minutes)',
            ]);

            foreach ($trips as $trip) {
                fputcsv($out, array_map([$this, 'safeCell'], [
                    $trip->reference,
                    ucfirst($trip->status),
                    $trip->driver->user->name,
                    $trip->truck->plate_no,
                    $trip->origin_address,
                    $trip->destination_address,
                    $trip->departure_at->format('Y-m-d H:i'),
                    $trip->expected_arrival_at->format('Y-m-d H:i'),
                    $trip->finished_at?->format('Y-m-d H:i'),
                    $trip->rest_periods_count,
                    intdiv((int) $trip->rest_periods_sum_duration_seconds, 60),
                ]));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // Finished trips, narrowed by the search box and the status filter.
    private function filtered(string $q, ?string $status)
    {
        return Schedule::with(['driver.user', 'truck', 'proof'])
            ->whereIn('status', self::FINISHED)
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($w) use ($q, $like) {
                    $w->where('origin_address', 'ilike', $like)
                        ->orWhere('destination_address', 'ilike', $like)
                        ->orWhereHas(
                            'truck',
                            fn ($t) => $t->where('plate_no', 'ilike', $like),
                        )
                        ->orWhereHas(
                            'driver.user',
                            fn ($u) => $u->where('name', 'ilike', $like),
                        );

                    if (preg_match('/^(fp-?)?(\d+)$/i', $q, $m)) {
                        $w->orWhere('id', (int) $m[2]);
                    }
                });
            })
            ->when(
                in_array($status, self::FINISHED, true),
                fn ($query) => $query->where('status', $status),
            )
            ->latest('updated_at');
    }

    // Stops Excel from running a cell as a formula (for example an address typed as "=1+1").
    private function safeCell($value)
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }
}