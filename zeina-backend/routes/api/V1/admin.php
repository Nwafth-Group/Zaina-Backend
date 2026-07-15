<?php

use App\Http\Controllers\Api\V1\Admin\AdminPointsController;
use App\Http\Controllers\Api\V1\Admin\AdsController;
use App\Http\Controllers\Api\V1\Admin\AnnouncementController;
use App\Http\Controllers\Api\V1\Admin\AreaController;
use App\Http\Controllers\Api\V1\Admin\AuthController;
use App\Http\Controllers\Api\V1\Admin\BookingController;
use App\Http\Controllers\Api\V1\Admin\CategoryController;
use App\Http\Controllers\Api\V1\Admin\CityController;
use App\Http\Controllers\Api\V1\Admin\CompanyInfoController;
use App\Http\Controllers\Api\V1\Admin\CountryController;
use App\Http\Controllers\Api\V1\Admin\CouponController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\EmployeeController;
use App\Http\Controllers\Api\V1\Admin\GovrenrateController;
use App\Http\Controllers\Api\V1\Admin\KysController;
use App\Http\Controllers\Api\V1\Admin\LegalDocumentController;
use App\Http\Controllers\Api\V1\Admin\MobileNotificationController;
use App\Http\Controllers\Api\V1\Admin\ProfileController;
use App\Http\Controllers\Api\V1\Admin\ProviderController;
use App\Http\Controllers\Api\V1\Admin\ReportController;
use App\Http\Controllers\Api\V1\Admin\ReviewController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\ServiceController;
use App\Http\Controllers\Api\V1\Admin\ServicePriceController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Admin\WorkerController;
use App\Http\Middleware\IsAdmin;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
    });

    Route::post('auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    Route::get('kyc/download/{media}', [UserController::class, 'downloadKycDoc'])
        ->name('admin.kyc.download')
        ->middleware('signed');

    Route::middleware(['auth:sanctum', IsAdmin::class])->group(function () {

        Route::get('dashboard/stats', [DashboardController::class, 'stats']);

        // Company Information Settings
        Route::get('company-info', [CompanyInfoController::class, 'show']);
        Route::post('company-info', [CompanyInfoController::class, 'update']);

        // Current admin's live identity/roles/permissions (keeps the UI in sync).
        Route::get('me', [ProfileController::class, 'me']);

        // Current admin's own profile (self-service).
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::put('profile/password', [ProfileController::class, 'updatePassword']);

        Route::prefix('users')->group(function () {
            Route::post('{user}/verify', [UserController::class, 'verifyUser']);
            Route::get('/', [UserController::class, 'index'])->middleware('permission:users.view');
            Route::get('/{user}', [UserController::class, 'show'])->middleware('permission:users.view');
            Route::put('/{user}', [UserController::class, 'update'])->middleware('permission:users.edit');
            Route::patch('{user}/toggle-status', [UserController::class, 'toggleStatus'])->middleware('permission:users.edit');
            Route::post('/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('permission:users.edit');
            Route::put('/{user}/verify', [UserController::class, 'verifyUser']);
        });

        // Employee management is restricted to Super Admins (operations staff
        // cannot list, add or manage employees). Gated per-action via the
        // `employees.*` permissions, which only the Super Admin role holds.
        // Employees have no hard delete: their lifecycle is managed via the
        // activity status (toggle-status), so the destroy verb is unregistered.
        Route::prefix('employees')->group(function () {
            Route::get('/', [EmployeeController::class, 'index'])->middleware('permission:employees.view');
            Route::post('/', [EmployeeController::class, 'store'])->middleware('permission:employees.create');
            Route::get('{employee}', [EmployeeController::class, 'show'])->middleware('permission:employees.view');
            Route::match(['put', 'patch'], '{employee}', [EmployeeController::class, 'update'])->middleware('permission:employees.edit');
            Route::patch('{employee}/toggle-status', [EmployeeController::class, 'toggleStatus'])->middleware('permission:employees.edit');
        });

        Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view');
        Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.create');
        Route::get('roles/permissions', [RoleController::class, 'permissions'])->middleware('permission:roles.view');
        Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:roles.view');
        Route::match(['put', 'patch'], 'roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.edit');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete');
        Route::post('roles/{role}/duplicate', [RoleController::class, 'duplicate'])->middleware('permission:roles.create');

        // Read-only location lookups feed admin form dropdowns (service coverage &
        // pricing, worker assignment). Available to any admin: reading reference
        // data must not require the location.edit *write* permission, which the
        // services/workers management area itself does not require either.
        Route::get('cities', [CityController::class, 'index']);
        Route::get('areas', [AreaController::class, 'index']);
        Route::get('govrenrates', [GovrenrateController::class, 'index']);

        // Location management (writes) stays gated behind the location.edit permission.
        Route::middleware('permission:location.edit')->group(function () {
            Route::apiResource('cities', CityController::class)->except('index');
            Route::post('cities/{city}/toggle-status', [CityController::class, 'toggleStatus']);

            Route::apiResource('countries', CountryController::class);
            Route::post('countries/{country}/toggle-status', [CountryController::class, 'toggleStatus']);

            Route::apiResource('areas', AreaController::class)->except('index');
            Route::post('areas/{area}/toggle-status', [AreaController::class, 'toggleStatus']);
        });

        // kyc routes
        //  Route::prefix('kyc')->group(function (){

        // Route::get('/pending', [KysController::class, 'index']);
        // Route::get('/{user}', [KysController::class, 'show']);
        // Route::post('/{user}/approve', [KysController::class, 'approve']);
        // Route::post('/{user}/reject', [KysController::class, 'reject']);
        // Route::get('/media/{mediaId}', [KysController::class, 'viewPrivateMedia']);
        //  });

        Route::apiResource('categories', CategoryController::class);
        Route::post('categories/{category}/toggle-status', [CategoryController::class, 'toggleStatus']);
        Route::get('services/{service}/details', [ServiceController::class, 'details']);
        Route::apiResource('services', ServiceController::class);
        Route::post('services/{service}/toggle-status', [ServiceController::class, 'toggleStatus']);
        Route::apiResource('servicesprices', ServicePriceController::class);
        Route::post('servicesprices/estimated', [ServicePriceController::class, 'getEstimatedPrice']);
        Route::get('workers/nationalities', [WorkerController::class, 'nationalities']);
        Route::get('workers/{worker}/details', [WorkerController::class, 'details']);
        Route::apiResource('workers', WorkerController::class);
        Route::post('workers/{worker}/toggle-status', [WorkerController::class, 'changeStatus']);

        // Bookings are created by customers; the admin area lists, views and
        // changes their status (logged to booking_histories).
        Route::get('bookings', [BookingController::class, 'index']);
        Route::get('bookings/{booking}', [BookingController::class, 'show']);
        Route::patch('bookings/{booking}/status', [BookingController::class, 'updateStatus']);
        Route::get('bookings/{booking}/eligible-workers', [BookingController::class, 'eligibleWorkers']);
        Route::post('bookings/{booking}/assign-workers', [BookingController::class, 'assignWorkers']);
        Route::post('bookings/{booking}/reschedule', [BookingController::class, 'reschedule']);

        // Home banners (ads) shown in the customer app.
        Route::apiResource('ads', AdsController::class);
        Route::patch('ads/{ad}/toggle-status', [AdsController::class, 'toggleStatus']);

        // Announcements shown in the customer app notifications tab.
        Route::apiResource('announcements', AnnouncementController::class);

        // Mobile Push Notifications
        Route::apiResource('mobile-notifications', MobileNotificationController::class);
        Route::patch('mobile-notifications/{mobile_notification}/cancel', [MobileNotificationController::class, 'cancel']);

        // Coupons management
        Route::apiResource('coupons', CouponController::class);

        // Loyalty Points Management
        Route::get('points/rules', [AdminPointsController::class, 'getRules']);
        Route::post('points/rules', [AdminPointsController::class, 'storeRule']);
        Route::put('points/rules/{id}', [AdminPointsController::class, 'updateRule']);
        Route::delete('points/rules/{id}', [AdminPointsController::class, 'destroyRule']);

        Route::get('points/settings', [AdminPointsController::class, 'getSettings']);
        Route::post('points/settings', [AdminPointsController::class, 'storeSettings']);

        Route::get('points/services', [AdminPointsController::class, 'getServices']);
        Route::put('points/services/{id}', [AdminPointsController::class, 'updateServicePoints']);

        Route::get('points/transactions', [AdminPointsController::class, 'getTransactions']);
        Route::post('points/manual-adjustment', [AdminPointsController::class, 'manualAdjustment']);

        // Legal documents
        Route::apiResource('legal-documents', LegalDocumentController::class);

        // Reviews
        Route::get('reviews', [ReviewController::class, 'index']);

        // Reports
        Route::get('reports', [ReportController::class, 'index']);
        Route::get('reports/{report}', [ReportController::class, 'show']);
        Route::post('reports/{report}/assign', [ReportController::class, 'assign']);
        Route::patch('reports/{report}/status', [ReportController::class, 'updateStatus']);
        Route::post('reports/{report}/notes', [ReportController::class, 'addNote']);

        //  Route::middleware('permission:providers.view')->prefix('providers')->group(function () {
        //         Route::get('/', [ProviderController::class, 'index']);
        //         Route::post('/', [ProviderController::class, 'store'])->middleware('permission:providers.create');
        //         Route::put('/{provider}', [ProviderController::class, 'update'])->middleware('permission:providers.edit');
        //         Route::patch('/{provider}/toggle-status', [ProviderController::class,'toggleStatus'])->middleware('permission:providers.deactivate');
        //         // US-02: Mapping Route
        //         Route::post('/{provider}/map', [ProviderController::class, 'mapProvider'])->middleware('permission:provider_eligibility.edit');
        //     });

    });

});
