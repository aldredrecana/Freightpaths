<?php

namespace App\Http\Controllers;

use App\Services\StaffAlerts;

class AlertController extends Controller
{
    // The bell in the top bar asks this every 30 seconds.
    public function __invoke(StaffAlerts $alerts)
    {
        $items = $alerts->all();

        return response()->json([
            'count' => $items->count(),
            'items' => $items->take(20)->values(),
        ]);
    }
}