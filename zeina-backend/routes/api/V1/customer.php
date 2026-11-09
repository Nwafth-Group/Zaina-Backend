<?php

use App\Http\Controllers\Api\V1\Customer\AddressController;
use App\Http\Controllers\Api\V1\Customer\AuthController;
use App\Http\Controllers\Api\V1\Customer\BookingContractController;
use App\Http\Controllers\Api\V1\Customer\BookingController;
use App\Http\Controllers\Api\V1\Customer\CategoryController;
use App\Http\Controllers\Api\V1\Customer\CompanyInfoController;
use App\Http\Controllers\Api\V1\Customer\CouponController;
use App\Http\Controllers\Api\V1\Customer\HomeController;
use App\Http\Controllers\Api\V1\Customer\KycController;
use App\Http\Controllers\Api\V1\Customer\LegalDocumentController;
use App\Http\Controllers\Api\V1\Customer\NotificationandAnnouncementController;
use App\Http\Controllers\Api\V1\Customer\PointsController;
use App\Http\Controllers\Api\V1\Customer\ProfileController;
use App\Http\Controllers\Api\V1\Customer\ReviewController;
use App\Http\Controllers\Api\V1\Customer\SearchController;
use App\Http\Controllers\Api\V1\Customer\ServiceController;
use App\Http\Controllers\Api\V1\Customer\UserCardController;
use App\Http\Controllers\Api\V1\Customer\WalletController;
use App\Http\Controllers\Api\V1\Notification\DeviceTokenController;
use App\Http\Middleware\IsCustomer;
use Illuminate\Support\Facades\Route;

Route::prefix('customer')->group(function () {

    Route::get('search', [SearchController::class, 'index']);
    Route::get('filter', [SearchController::class, 'filter']);
    Route::get('/bookings/public-contract/{booking}', [BookingContractController::class, 'viewContractPublicly'])
        ->name('bookings.public_contract');

    Route::prefix('auth')->group(function () {
        // Route::post('register', [AuthController::class, 'register']);
        // Route::post('verify-register', [AuthController::class, 'verifyRegister']);
        Route::post('send-otp', [AuthController::class, 'sendOtp']);
        Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
        Route::post('/verify', [KycController::class, 'submit'])->middleware('auth:sanctum');
    });

    // ==========================================
    // Protected Routes (Require Authentication)
    // ==========================================
    Route::get('home', [HomeController::class, 'index']);
    Route::get('/categories', [CategoryController::class, 'getMainCategories']); // 4
    Route::get('/categories/{category}/sub', [CategoryController::class, 'getSubcategories']); //
    Route::get('/services', [ServiceController::class, 'index']);
    Route::get('/services/{service}', [ServiceController::class, 'show']);
    Route::get('/services/{service}/available-visit-types', [ServiceController::class, 'getAvailableVisitTypes']);
    Route::get('/reviews/{service}', [ReviewController::class, 'index']); // 5

    Route::prefix('legal')->group(function () {
        Route::get('terms-conditions', [LegalDocumentController::class, 'getTerms']);
        Route::get('privacy', [LegalDocumentController::class, 'getPrivacy']);
        Route::get('about-us', [LegalDocumentController::class, 'getAboutUs']);
    });

    Route::get('company-info', [CompanyInfoController::class, 'show']);

    // fetch countries, governorates and cities
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::middleware(['auth:sanctum', IsCustomer::class])->group(function () {
        // Auth
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // Route::post('auth/send-change-phone-otp', [AuthController::class, 'sendUpdatePhoneOtp']);
        // Route::post('auth/verify-change-phone', [AuthController::class, 'verifyAndUpdatePhone']);

        Route::post('notification/update-device-token', [DeviceTokenController::class, 'updateToken']);

        // Profile
        Route::prefix('profile')->group(function () {
            // Donee
            Route::post('complete', [ProfileController::class, 'completeProfile']); // Donee
            Route::post('send-delete-otp', [ProfileController::class, 'sendDeleteOtp']);
            Route::post('delete', [ProfileController::class, 'deleteAccount']);
        });
        // Addresses
        Route::apiResource('addresses', AddressController::class);
        Route::post('address/{id}/set-default', [AddressController::class, 'setDefault']);
        // Home page

        // Services coverage
        Route::get('/services/{service}/check-coverage', [ServiceController::class, 'checkCoverage']);

        // Bookings
        Route::match(['get', 'post'], '/bookings/available-dates', [BookingController::class, 'availableDates']);
        Route::post('/bookings/validate', [BookingController::class, 'validateBooking']);
        Route::post('/bookings/contract-preview', [BookingContractController::class, 'previewContract']);
        Route::get('/bookings/{booking}/contract', [BookingContractController::class, 'downloadContract']);
        Route::get('/reports', [BookingController::class, 'myReports']);
        Route::get('/reports/{report}', [BookingController::class, 'showReport']);
        Route::apiResource('bookings', BookingController::class);
        Route::get('bookings/{booking}/shifts/schedule', [BookingController::class, 'getRescheduleShifts']); //
        Route::post('bookings/{booking}/reschedule/send-otp', [BookingController::class, 'sendRescheduleOtp']);
        Route::post('bookings/{booking}/reschedule', [BookingController::class, 'reschedule']);
        Route::post('bookings/{booking}/cancel/send-otp', [BookingController::class, 'sendCancelOtp']);
        Route::put('bookings/{booking}/cancel', [BookingController::class, 'cancel']);
        Route::post('bookings/{id}/report', [BookingController::class, 'report']);
        Route::post('bookings/{id}/review', [BookingController::class, 'review']);

        // wallet endpoints

        Route::get('wallet', [WalletController::class, 'index']);
        Route::post('wallet/top-up', [WalletController::class, 'topUp']);

        Route::get('cards', [UserCardController::class, 'index']);
        Route::post('cards', [UserCardController::class, 'store']);
        Route::delete('cards/{id}', [UserCardController::class, 'destroy']);
        // Points
        Route::prefix('points')->group(function () {
            Route::get('/summary', [PointsController::class, 'summary']);
            Route::get('/history', [PointsController::class, 'history']);
            Route::get('/earn-points', [PointsController::class, 'earnPoints']);
            Route::post('/complete-task', [PointsController::class, 'completeTask']);
            Route::post('/generate-referral-code', [PointsController::class, 'generateReferralCode']);
        });

        Route::apiResource('coupons', CouponController::class)->only(['index', 'show']);

        Route::prefix('notifications')->group(function () {
            Route::get('unread-count', [NotificationandAnnouncementController::class, 'unreadCount']);  // عدد الإشعارات غير المقروءة
            Route::get('/', [NotificationandAnnouncementController::class, 'index']);            // جلب القائمة حسب الـ Tab
            Route::post('/{id}/read', [NotificationandAnnouncementController::class, 'markAsRead']); // قراءة عنصر (إشعار أو إعلان)
            Route::post('/read-all', [NotificationandAnnouncementController::class, 'markAllAsRead']); // قراءة الكل
        });

    });

});
