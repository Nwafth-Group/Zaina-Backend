<?php

use App\Http\Controllers\Api\V1\Customer\AdController;
use App\Http\Controllers\Api\V1\Customer\AddressController;
use App\Http\Controllers\Api\V1\Customer\AuthController;
use App\Http\Controllers\Api\V1\Customer\CategoryController;
use App\Http\Controllers\Api\V1\Customer\HomeController;
use App\Http\Controllers\Api\V1\Customer\ProfileController;
use App\Http\Controllers\Api\V1\Customer\SearchController;
use App\Http\Controllers\Api\V1\Customer\ServiceController;
use App\Http\Controllers\Api\V1\Customer\TestController;
use Illuminate\Support\Facades\Route;

Route::prefix('customer')->group(function () {

    Route::get('test', [TestController::class, 'test']);
    Route::get('search', [SearchController::class, 'index']);
    Route::get('filter', [SearchController::class, 'filter']);

    Route::prefix('auth')->group(function () {
        // Route::post('register', [AuthController::class, 'register']);
        // Route::post('verify-register', [AuthController::class, 'verifyRegister']);
        Route::post('send-otp', [AuthController::class, 'sendotp']);
        Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
    });

    // ==========================================
    // Protected Routes (Require Authentication)
    // ==========================================
    Route::get('home',[HomeController::class,'index']);
    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::post('auth/logout', [AuthController::class, 'logout']);
        // Profile
        Route::prefix('profile')->group(function () {
            Route::post('complete', [ProfileController::class, 'completeProfile']);
            Route::post('send-delete-otp', [ProfileController::class, 'sendDeleteOtp']);
            Route::post('delete', [ProfileController::class, 'deleteAccount']);
        });
        // Addresses
        Route::apiResource('addresses', AddressController::class);
        Route::post('address/{id}/set-default', [AddressController::class, 'setDefault']); 
        //Home page

       Route::get('/categories', [CategoryController::class, 'getMainCategories']);
       Route::get('/categories/{category}/sub', [CategoryController::class, 'getSubcategories']);
       Route::get('/services/{service}', [ServiceController::class, 'show']); 
       Route::get('services/{service}/shifts', [ServiceController::class, 'getShiftsForDate']);
       Route::get('/services/{service}/available-workers', [ServiceController::class, 'getAvailableWorkers']);

    //(Services)
        Route::get('/services', [ServiceController::class, 'index']); // لجلب كل الخدمات مع Paginate
        Route::get('/categories/{category}/services', [ServiceController::class, 'getServicesByCategory']);


        
    });

});