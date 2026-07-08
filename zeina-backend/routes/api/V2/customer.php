<?php

use App\Http\Controllers\Api\V2\Customer\ServiceController;
use App\Http\Controllers\Api\V2\Customer\HomeController;
use App\Http\Controllers\Api\V2\Customer\BookingController;
use App\Http\Controllers\Api\V2\Customer\CouponController;
use Illuminate\Support\Facades\Route;

Route::prefix('customer')->group(function () {
    Route::get('/home', [HomeController::class, 'index']);
    Route::get('/services', [ServiceController::class, 'index']);
    Route::get('/services/{service}', [ServiceController::class, 'show']);
    Route::get('/services/{service}/available-visit-types', [ServiceController::class, 'getAvailableVisitTypes']);
    
    // Authenticated customer booking routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/services/{service}/check-coverage', [ServiceController::class, 'checkCoverage']);
        Route::post('/bookings/validate', [BookingController::class, 'validateBooking']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::get('/bookings', [BookingController::class, 'index']);
        Route::get('/bookings/{booking}', [BookingController::class, 'show']);

        // V2 Coupons
        Route::get('/coupons', [CouponController::class, 'index']);
        Route::get('/coupons/{id}', [CouponController::class, 'show']);
    });
});
