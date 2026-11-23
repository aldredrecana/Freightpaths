<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeocodeController extends Controller
{
    // How many versions of one address we try (the free service asks for light use).
    private const MAX_TRIES = 3;

    // Turns an address into a map point, for the "Find on map" button in the trip form.
    // Uses the free OpenStreetMap search (Nominatim). Results are kept for a day,
    // so the same address is never asked twice (their rules ask for light use).
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:300'],
        ])['q']);

        $key = 'geocode:'.md5(mb_strtolower($q));

        $result = Cache::remember($key, now()->addDay(), function () use ($q) {
            foreach (array_slice($this->candidates($q), 0, self::MAX_TRIES) as $i => $text) {
                if ($i > 0) {
                    usleep(1_100_000); // their rule: at most one search per second
                }

                $first = $this->search($text);

                if ($first === null) {
                    return null; // service down or no internet: not cached
                }

                if ($first !== false) {
                    return [
                        'found' => true,
                        'lat' => (float) $first['lat'],
                        'lng' => (float) $first['lon'],
                        'label' => $first['display_name'] ?? $text,
                        // True when only a shorter version of the address matched.
                        'approximate' => $i > 0,
                    ];
                }
            }

            return ['found' => false];
        });

        if ($result === null) {
            Cache::forget($key);

            return response()->json([
                'found' => false,
                'message' => 'The address search is not reachable right now. You can click on the map to place the pin.',
            ], 503);
        }

        return response()->json($result);
    }

        // Map point to address: when a pin is placed and the address box is still empty.
    public function reverse(Request $request)
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        // Points about 1 m apart share the same answer.
        $lat = round((float) $data['lat'], 5);
        $lng = round((float) $data['lng'], 5);
        $key = "geocode:reverse:{$lat},{$lng}";

        $label = Cache::get($key);

        if ($label === null) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => config('app.name').' trip planner ('.config('app.url').')',
                ])
                    ->timeout(8)
                    ->get(config('freightpath.reverse_geocode_url'), [
                        'lat' => $lat,
                        'lon' => $lng,
                        'format' => 'jsonv2',
                        'zoom' => 18, // street / building level
                    ]);
            } catch (\Throwable $e) {
                $response = null;
            }

            if (! $response || ! $response->ok()) {
                return response()->json(['found' => false], 503);
            }

            $label = (string) ($response->json('display_name') ?? '');
            Cache::put($key, $label, now()->addDay());
        }

        return response()->json($label === ''
            ? ['found' => false]
            : ['found' => true, 'label' => $this->shorten($label)]);
    }

    // "Building, Street, Barangay, Town, Province, Region, 4000, Philippines"
    // -> without the region, postal code and country (they are long and not needed on a trip).
    private function shorten(string $label): string
    {
        $parts = array_map('trim', explode(',', $label));
        $parts = array_filter($parts, fn ($p) => $p !== 'Philippines'
            && ! preg_match('/^\d{4}$/', $p)
            && ! preg_match('/^(Calabarzon|Mimaropa|Metro Manila|National Capital Region|Region [IVX]+|.*Region)$/i', $p));

        return implode(', ', $parts);
    }

    // The address as typed, then shorter versions the free search understands better:
    // "Rizal Park (Luneta), Roxas Boulevard, Ermita, Manila, 1000"
    //   -> "Rizal Park, Roxas Boulevard, Ermita, Manila"   (no brackets, no postal code)
    //   -> "Roxas Boulevard, Ermita, Manila"                (first part dropped)
    private function candidates(string $q): array
    {
        $clean = preg_replace('/\([^)]*\)/', '', $q);       // text in brackets
        $clean = preg_replace('/\b\d{4}\b/', '', $clean);    // 4-digit postal codes
        $parts = array_values(array_filter(array_map('trim', explode(',', $clean))));

        $list = [$q, implode(', ', $parts)];

        for ($i = 1; $i <= count($parts) - 2; $i++) {
            $list[] = implode(', ', array_slice($parts, $i));
        }

        return array_values(array_unique(array_filter($list, fn ($s) => mb_strlen($s) >= 3)));
    }

    // One search. Returns the first match, false when nothing matched, or null when the service failed.
    private function search(string $text): array|false|null
    {
        try {
            $response = Http::withHeaders([
                // Their rules ask every app to say who it is.
                'User-Agent' => config('app.name').' trip planner ('.config('app.url').')',
            ])
                ->timeout(8)
                ->get(config('freightpath.geocode_url'), [
                    'q' => $text,
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'countrycodes' => config('freightpath.geocode_country'),
                ]);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        return $response->json('0') ?? false;
    }
}