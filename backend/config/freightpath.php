<?php

return [
    // A pending or declined trip is flagged URGENT this many hours before departure.
    'late_window_hours' => (int) env('FREIGHTPATH_LATE_WINDOW_HOURS', 3),

    // After this many minutes without acceptance, "Awaiting driver" turns red.
    'response_window_minutes' => (int) env('FREIGHTPATH_RESPONSE_WINDOW_MINUTES', 30),

    'public_registration' => (bool) env('FREIGHTPATH_PUBLIC_REGISTRATION', false),

    'dispatcher_phone' => env('FREIGHTPATH_DISPATCH_PHONE'),

        // A driver counts as resting when GPS points stay inside this circle (meters)...
    'rest_radius_m' => (int) env('FREIGHTPATH_REST_RADIUS_M', 100),

    // ...for at least this many minutes.
    'rest_min_minutes' => (int) env('FREIGHTPATH_REST_MINUTES', 15),

    // If the phone is silent longer than this, that time is not counted as rest.
    'rest_max_gap_minutes' => (int) env('FREIGHTPATH_REST_MAX_GAP_MINUTES', 10),

    // GPS points less accurate than this (meters) are ignored.
    'rest_max_accuracy_m' => (int) env('FREIGHTPATH_REST_MAX_ACCURACY_M', 100),

    'rest_max_speed_ms' => (float) env('FREIGHTPATH_REST_MAX_SPEED_MS', 2.0),
    'rest_merge_gap_minutes' => (int) env('FREIGHTPATH_REST_MERGE_GAP_MINUTES', 10),
    'rest_merge_distance_m' => (float) env('FREIGHTPATH_REST_MERGE_DISTANCE_M', 200),

    
    // Map tiles (the map pictures). The free OpenStreetMap server is for light use only;
    // a company can paste its own provider's URL and credit line here (see .env).
    'map_tile_url' => env('FREIGHTPATH_MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
    'map_attribution' => env('FREIGHTPATH_MAP_ATTRIBUTION', '&copy; OpenStreetMap contributors'),

    
    // Address search for the "Find on map" button (free OpenStreetMap Nominatim, light use only).
    'geocode_url' => env('FREIGHTPATH_GEOCODE_URL', 'https://nominatim.openstreetmap.org/search'),
    'reverse_geocode_url' => env('FREIGHTPATH_REVERSE_GEOCODE_URL', 'https://nominatim.openstreetmap.org/reverse'),
    'geocode_country' => env('FREIGHTPATH_GEOCODE_COUNTRY', 'ph'), // search only inside the Philippines

    // Drawing the driven route on the roads ("map matching"). GPS points are a few meters off,
    // so the server asks OSRM (free OpenStreetMap routing) which roads the points belong to.
    // If OSRM cannot be reached, the map simply draws the GPS points as they are.
    'snap_to_roads' => (bool) env('FREIGHTPATH_SNAP_TO_ROADS', true),
    'osrm_url' => env('FREIGHTPATH_OSRM_URL', 'https://router.project-osrm.org'),
    'path_max_accuracy_m' => (int) env('FREIGHTPATH_PATH_MAX_ACCURACY_M', 50), // rougher points are left out of the line
    'path_gap_seconds' => (int) env('FREIGHTPATH_PATH_GAP_SECONDS', 120), // silence longer than this...
    'path_gap_meters' => (int) env('FREIGHTPATH_PATH_GAP_METERS', 300), // ...over this distance = lost signal
];
