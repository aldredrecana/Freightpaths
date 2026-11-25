<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BreakController;
use App\Http\Controllers\Api\DeliveryProofController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LocationController;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1');

Route::middleware(['auth:sanctum', 'role:driver'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/me', [AuthController::class, 'updateProfile']);
    Route::post('/me/password', [AuthController::class, 'changePassword'])->middleware('throttle:6,1');

    Route::get('/trips', [TripController::class, 'index']);
    Route::get('/trips/{schedule}', [TripController::class, 'show']);
    Route::get('/trips/{schedule}/track', [TripController::class, 'track']);
    Route::get('/trips/{schedule}/route', [TripController::class, 'route']);
    Route::get('/trips/{schedule}/incidents', [IncidentController::class, 'index']);
    Route::post('/trips/{schedule}/incidents', [IncidentController::class, 'store'])->middleware('throttle:6,1');
    Route::post('/trips/{schedule}/proof', [DeliveryProofController::class, 'store'])->middleware('throttle:10,1');
    Route::post('/trips/{schedule}/break/start', [BreakController::class, 'start'])->middleware('throttle:10,1');
    Route::post('/trips/{schedule}/break/end', [BreakController::class, 'end'])->middleware('throttle:10,1');

    // Same methods the website uses, so the rules stay in one place.
    Route::post('/trips/{schedule}/status', [ScheduleController::class, 'updateStatus']);
    Route::post('/trips/{schedule}/decline', [ScheduleController::class, 'decline']);
    

    Route::post('/locations', [LocationController::class, 'store'])->middleware('throttle:120,1');


});
