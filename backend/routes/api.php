<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LocationController;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1');

Route::middleware(['auth:sanctum', 'role:driver'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/trips', [TripController::class, 'index']);
    Route::get('/trips/{schedule}', [TripController::class, 'show']);

    // Same methods the website uses, so the rules stay in one place.
    Route::post('/trips/{schedule}/status', [ScheduleController::class, 'updateStatus']);
    Route::post('/trips/{schedule}/decline', [ScheduleController::class, 'decline']);

    Route::post('/locations', [LocationController::class, 'store'])->middleware('throttle:120,1');
});
