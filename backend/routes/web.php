<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GeocodeController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TruckController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\AlertController;



Route::get(
    '/',
    fn () => redirect()->route(Auth::check() ? 'dashboard' : 'login'),
);

Route::get('/dashboard', DashboardController::class)
        ->middleware(['auth', 'verified'])
        ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name(
        'profile.edit',
    );
    Route::patch('/profile', [ProfileController::class, 'update'])->name(
        'profile.update',
    );
});



require __DIR__.'/auth.php';

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::resource('drivers', DriverController::class)->except([
        'index',
        'show',
    ]); 
    // Signs the driver out of the phone app (for a lost or changed phone).
    Route::post('/drivers/{driver}/logout-phone', [DriverController::class, 'logoutPhone'])
        ->whereNumber('driver')
        ->name('drivers.logout-phone');
});

Route::middleware(['auth', 'role:admin,dispatcher'])
    ->get('/drivers', [DriverController::class, 'index'])
    ->name('drivers.index');

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::resource('trucks', TruckController::class)->except([
        'index',
        'show',
    ]);
});

Route::middleware(['auth', 'role:admin,dispatcher'])
    ->get('/trucks', [TruckController::class, 'index'])
    ->name('trucks.index');

Route::middleware(['auth', 'role:admin,dispatcher'])->group(function () {
    Route::resource('schedules', ScheduleController::class)->except(['show']);
    Route::post('/schedules/{schedule}/reassign', [
        ScheduleController::class,
        'reassign',
    ])
        ->whereNumber('schedule')
        ->name('schedules.reassign');
});

Route::middleware('auth')->group(function () {
    Route::get('/schedules/{schedule}', [ScheduleController::class, 'show'])
        ->whereNumber('schedule')
        ->name('schedules.show');

    Route::post('/schedules/{schedule}/decline', [
        ScheduleController::class,
        'decline',
    ])
        ->middleware('role:driver')
        ->whereNumber('schedule')
        ->name('schedules.decline');

    Route::post('/schedules/{schedule}/status', [
        ScheduleController::class,
        'updateStatus',
    ])
        ->whereNumber('schedule')
        ->name('schedules.status');

    Route::get('/my-deliveries', [ScheduleController::class, 'mine'])
        ->middleware('role:driver')
        ->name('schedules.mine');
});

Route::middleware(['auth', 'role:admin,dispatcher'])
    ->get('/drivers/{driver}', [DriverController::class, 'show'])
    ->whereNumber('driver')
    ->name('drivers.show');

Route::middleware(['auth', 'role:admin,dispatcher'])
    ->get('/transactions', [TransactionController::class, 'index'])
    ->name('transactions.index');

Route::middleware(['auth', 'role:admin,dispatcher'])
    ->get('/transactions/export', [TransactionController::class, 'export'])
    ->name('transactions.export');

Route::middleware(['auth', 'role:admin,dispatcher'])
    ->get('/trucks/{truck}', [TruckController::class, 'show'])
    ->whereNumber('truck')
    ->name('trucks.show');

Route::middleware(["auth", "role:admin,dispatcher"])
    ->get("/schedules/{schedule}/tracking", [TrackingController::class, "show"])
    ->whereNumber("schedule")
    ->name("schedules.tracking");

Route::middleware(["auth", "role:admin,dispatcher"])->group(function () {
    // Alerts for the bell in the top bar (declined trips, no signal, not accepted yet).
    Route::get("/alerts", AlertController::class)->name("alerts.index");
    
    // Address to map point, for the "Find on map" button in the trip form.
    Route::get("/geocode", GeocodeController::class)
        ->middleware("throttle:30,1")
        ->name("geocode");
        Route::get("/geocode/reverse", [GeocodeController::class, "reverse"])
        ->middleware("throttle:30,1")
        ->name("geocode.reverse");
    Route::get("/route-preview", [MapController::class, "route"])
        ->middleware("throttle:60,1")
        ->name("route.preview");

    Route::get("/map", [MapController::class, "index"])->name("map.index");
    Route::get("/incidents", [IncidentController::class, "index"])->name("incidents.index");
    Route::post("/incidents/{incident}/resolve", [IncidentController::class, "resolve"])->name("incidents.resolve");
    Route::get("/map/data", [MapController::class, "data"])->name("map.data");
});