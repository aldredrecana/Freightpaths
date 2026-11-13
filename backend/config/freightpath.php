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
];
