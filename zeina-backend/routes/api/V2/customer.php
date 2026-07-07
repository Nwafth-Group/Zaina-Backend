<?php

use App\Http\Controllers\Api\V2\Customer\ServiceController;
use App\Http\Controllers\Api\V2\Customer\HomeController;
use App\Http\Controllers\Api\V2\Customer\BookingController;
use Illuminate\Support\Facades\Route;

Route::prefix('customer')->group(function () {
    Route::get('/home', [HomeController::class, 'index']);
    Route::get('/services', [ServiceController::class, 'index']);
    Route::get('/services/{service}', [ServiceController::class, 'show']);
    Route::get('services/{service}/shifts', [ServiceController::class, 'getShiftsForDate']);
    Route::get('/services/{service}/available-workers', [ServiceController::class, 'getAvailableWorkers']);
    
    // Authenticated customer booking routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/services/{service}/check-coverage', [ServiceController::class, 'checkCoverage']);
        Route::post('/bookings/validate', [BookingController::class, 'validateBooking']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::get('/bookings', [BookingController::class, 'index']);
        Route::get('/bookings/{booking}', [BookingController::class, 'show']);
    });
});
