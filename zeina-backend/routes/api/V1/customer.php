<?php

use App\Http\Controllers\Api\V1\Customer\AdController;
use App\Http\Controllers\Api\V1\Customer\AddressController;
use App\Http\Controllers\Api\V1\Customer\AuthController;
use App\Http\Controllers\Api\V1\Customer\BookingController;
use App\Http\Controllers\Api\V1\Customer\CategoryController;
use App\Http\Controllers\Api\V1\Customer\CouponController;
use App\Http\Controllers\Api\V1\Customer\HomeController;
use App\Http\Controllers\Api\V1\Customer\KycController;
use App\Http\Controllers\Api\V1\Customer\LegalDocumentController;
use App\Http\Controllers\Api\V1\Customer\LocationController;
use App\Http\Controllers\Api\V1\Customer\PointsController;
use App\Http\Controllers\Api\V1\Customer\ProfileController;
use App\Http\Controllers\Api\V1\Customer\SearchController;
use App\Http\Controllers\Api\V1\Customer\ServiceController;
use App\Http\Controllers\Api\V1\Customer\TestController;
use App\Http\Controllers\Api\V1\Customer\UserCardController;
use App\Http\Controllers\Api\V1\Customer\WalletController;
use App\Http\Controllers\Api\V1\Notification\DeviceTokenController;
use App\Http\Middleware\IsCustomer;
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
        Route::post('/verify', [KycController::class, 'submit'])->middleware('auth:sanctum');
    });

    // ==========================================
    // Protected Routes (Require Authentication)
    // ==========================================
    Route::get('home',[HomeController::class,'index']);
    Route::get('/categories', [CategoryController::class,'getMainCategories']);//4
    Route::get('/categories/{category}/sub', [CategoryController::class, 'getSubcategories']);//
    Route::get('/services/{service}', [ServiceController::class, 'show']); //3
    Route::get('services/{service}/shifts', [ServiceController::class, 'getShiftsForDate']);//
    Route::get('services/{service}/available-duration', [ServiceController::class, 'getAvailableDurations']);//
    Route::get('/services/{service}/available-workers', [ServiceController::class, 'getAvailableWorkers']);
    Route::get('/services', [ServiceController::class, 'index']); // 1  // لجلب كل الخدمات مع Paginate
    Route::get('/categories/{category}/services', [ServiceController::class, 'getServicesByCategory']);// 2
    
    Route::prefix('legal')->group(function(){
        Route::get('terms-conditions', [LegalDocumentController::class, 'getTerms']);
        Route::get('privacy', [LegalDocumentController::class, 'getPrivacy']);

    });

    //fetch counttries and govrenrates and citites and areas .. 
    Route::get('locations/countries', [LocationController::class, 'countries']);
    Route::get('locations/governorates', [LocationController::class, 'governorates']);
    Route::get('locations/cities', [LocationController::class, 'cities']);
    Route::get('locations/areas', [LocationController::class, 'areas']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::middleware(['auth:sanctum' , IsCustomer::class])->group(function () {
        // Auth
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // Route::post('auth/send-change-phone-otp', [AuthController::class, 'sendUpdatePhoneOtp']);
        // Route::post('auth/verify-change-phone', [AuthController::class, 'verifyAndUpdatePhone']);

        Route::post('notification/update-device-token', [DeviceTokenController::class,'updateToken']);
        
        // Profile
        Route::prefix('profile')->group(function () {
           //Donee 
            Route::post('complete', [ProfileController::class, 'completeProfile']); //Donee 
            Route::post('send-delete-otp', [ProfileController::class, 'sendDeleteOtp']);
            Route::post('delete', [ProfileController::class, 'deleteAccount']);
        });
        // Addresses
        Route::apiResource('addresses', AddressController::class);
        Route::post('address/{id}/set-default', [AddressController::class, 'setDefault']); 
        //Home page

       //Bookings
       Route::get('/reports', [BookingController::class, 'myReports']);
       Route::get('/reports/{report}', [BookingController::class, 'showReport']);
       Route::apiResource('bookings', BookingController::class);
       Route::get('bookings/{booking}/shifts/schedule', [BookingController::class, 'getRescheduleShifts']);//
       Route::post('bookings/{booking}/reschedule', [BookingController::class, 'reschedule']);
       Route::put('bookings/{booking}/cancel', [BookingController::class,'cancel']);
       Route::post('bookings/{id}/report', [BookingController::class, 'report']);
       Route::post('bookings/{id}/review', [BookingController::class, 'review']);

       //wallet endpoints

       Route::get('wallet',[WalletController::class,'index']);
       Route::post('wallet/top-up',[WalletController::class,'topUp']);

       Route::get('cards', [UserCardController::class, 'index']);
       Route::post('cards', [UserCardController::class, 'store']);
       Route::delete('cards/{id}', [UserCardController::class, 'destroy']);
       //Points
       Route::prefix('points')->group(function (){
           Route::get('/summary', [PointsController::class, 'summary']);
           Route::get('/history', [PointsController::class, 'history']);
           Route::get('/earn-points',[PointsController::class, 'earnPoints']);
           Route::post('/complete-task', [PointsController::class, 'completeTask']);

       });

       Route::apiResource('coupons', CouponController::class)->only(['index', 'show']);

    });

});