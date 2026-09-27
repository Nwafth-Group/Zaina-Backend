<?php

use App\Http\Controllers\Api\V1\Admin\DriverController;
use App\Http\Controllers\Api\V1\Admin\DeparturePointController;
use App\Http\Controllers\Api\V1\Admin\ServiceZoneController;
use App\Http\Controllers\Api\V1\Admin\TransportDispatchController;
use App\Http\Controllers\Api\V1\Admin\AdminPointsController;
use App\Http\Controllers\Api\V1\Admin\AdsController;
use App\Http\Controllers\Api\V1\Admin\AnnouncementController;
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
use App\Http\Controllers\Api\V1\Customer\BookingContractController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Admin\WorkerController;
use App\Http\Controllers\Api\V1\Admin\TeamController;
use App\Http\Controllers\Api\V1\Admin\DailyOperationsController;
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

        // Drivers API
        Route::middleware('permission:drivers.view')->group(function () {
            Route::get('drivers', [DriverController::class, 'index']);
            Route::get('drivers/{driver}', [DriverController::class, 'show']);
        });
        Route::middleware('permission:drivers.create')->group(function () {
            Route::post('drivers', [DriverController::class, 'store']);
        });
        Route::middleware('permission:drivers.edit')->group(function () {
            Route::match(['put', 'patch'], 'drivers/{driver}', [DriverController::class, 'update']);
        });
        Route::middleware('permission:drivers.delete')->group(function () {
            Route::delete('drivers/{driver}', [DriverController::class, 'destroy']);
        });

        // Departure Points API
        Route::middleware('permission:departure_points.view')->group(function () {
            Route::get('departure-points', [DeparturePointController::class, 'index']);
            Route::get('departure-points/{departure_point}', [DeparturePointController::class, 'show']);
        });
        Route::middleware('permission:departure_points.create')->group(function () {
            Route::post('departure-points', [DeparturePointController::class, 'store']);
        });
        Route::middleware('permission:departure_points.edit')->group(function () {
            Route::match(['put', 'patch'], 'departure-points/{departure_point}', [DeparturePointController::class, 'update']);
        });
        Route::middleware('permission:departure_points.delete')->group(function () {
            Route::delete('departure-points/{departure_point}', [DeparturePointController::class, 'destroy']);
        });

        // Service Zones (Geo-fencing) API
        Route::middleware('permission:service_zones.view')->group(function () {
            Route::get('service-zones', [ServiceZoneController::class, 'index']);
            Route::get('service-zones/{service_zone}', [ServiceZoneController::class, 'show']);
            Route::post('service-zones/check-point', [ServiceZoneController::class, 'checkPoint']);
        });
        Route::middleware('permission:service_zones.create')->group(function () {
            Route::post('service-zones', [ServiceZoneController::class, 'store']);
        });
        Route::middleware('permission:service_zones.edit')->group(function () {
            Route::match(['put', 'patch'], 'service-zones/{service_zone}', [ServiceZoneController::class, 'update']);
        });
        Route::middleware('permission:service_zones.delete')->group(function () {
            Route::delete('service-zones/{service_zone}', [ServiceZoneController::class, 'destroy']);
        });

        // Transport & Dispatching API
        Route::middleware('permission:transport_dispatch.view')->prefix('transport-dispatch')->group(function () {
            Route::get('daily-overview', [TransportDispatchController::class, 'dailyOverview']);
            Route::get('region-details', [TransportDispatchController::class, 'regionGroupDetails']);
            Route::get('groups/{id}/export-excel', [TransportDispatchController::class, 'exportGroupExcel']);
            Route::put('groups/{id}', [TransportDispatchController::class, 'updateGroupDispatch'])->middleware('permission:transport_dispatch.edit');
        });

        Route::get('dashboard/stats', [DashboardController::class, 'stats']);

        // Company Information Settings
        Route::get('company-info', [CompanyInfoController::class, 'show']);
        Route::post('company-info', [CompanyInfoController::class, 'update']);

        Route::get('me', [ProfileController::class, 'me']);
        Route::get('me/notifications', [ProfileController::class, 'notifications']);
        Route::post('me/notifications/read', [ProfileController::class, 'markNotificationsAsRead']);

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
            Route::delete('{employee}', [EmployeeController::class, 'destroy'])->middleware('permission:employees.delete');
        });

        Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view');
        Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.create');
        Route::get('roles/permissions', [RoleController::class, 'permissions'])->middleware('permission:roles.view');
        Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:roles.view');
        Route::match(['put', 'patch'], 'roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.edit');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete');
        Route::post('roles/{role}/duplicate', [RoleController::class, 'duplicate'])->middleware('permission:roles.create');



        // kyc routes
        //  Route::prefix('kyc')->group(function (){

        // Route::get('/pending', [KysController::class, 'index']);
        // Route::get('/{user}', [KysController::class, 'show']);
        // Route::post('/{user}/approve', [KysController::class, 'approve']);
        // Route::post('/{user}/reject', [KysController::class, 'reject']);
        // Route::get('/media/{mediaId}', [KysController::class, 'viewPrivateMedia']);
        //  });

        // Categories
        Route::middleware('permission:categories.view')->group(function () {
            Route::get('categories', [CategoryController::class, 'index']);
            Route::get('categories/{category}', [CategoryController::class, 'show']);
        });
        Route::middleware('permission:categories.create')->group(function () {
            Route::post('categories', [CategoryController::class, 'store']);
        });
        Route::middleware('permission:categories.edit')->group(function () {
            Route::match(['put', 'patch'], 'categories/{category}', [CategoryController::class, 'update']);
            Route::post('categories/{category}/toggle-status', [CategoryController::class, 'toggleStatus']);
        });
        Route::middleware('permission:categories.delete')->group(function () {
            Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
        });

        // Services & Prices
        Route::middleware('permission:services.view')->group(function () {
            Route::get('services', [ServiceController::class, 'index']);
            Route::get('services/{service}', [ServiceController::class, 'show']);
            Route::get('services/{service}/details', [ServiceController::class, 'details']);
        });
        Route::middleware('permission:services.create')->group(function () {
            Route::post('services', [ServiceController::class, 'store']);
        });
        Route::middleware('permission:services.edit')->group(function () {
            Route::match(['put', 'patch'], 'services/{service}', [ServiceController::class, 'update']);
            Route::post('services/{service}/toggle-status', [ServiceController::class, 'toggleStatus']);
        });
        Route::middleware('permission:services.delete')->group(function () {
            Route::delete('services/{service}', [ServiceController::class, 'destroy']);
        });

        // Workers
        Route::middleware('permission:workers.view')->group(function () {
            Route::get('workers', [WorkerController::class, 'index']);
            Route::get('workers/nationalities', [WorkerController::class, 'nationalities']);
            Route::get('workers/{worker}', [WorkerController::class, 'show']);
            Route::get('workers/{worker}/details', [WorkerController::class, 'details']);
        });
        Route::middleware('permission:workers.create')->group(function () {
            Route::post('workers', [WorkerController::class, 'store']);
        });
        Route::middleware('permission:workers.edit')->group(function () {
            Route::match(['put', 'patch'], 'workers/{worker}', [WorkerController::class, 'update']);
            Route::post('workers/{worker}/toggle-status', [WorkerController::class, 'changeStatus']);
        });
        Route::middleware('permission:workers.delete')->group(function () {
            Route::delete('workers/{worker}', [WorkerController::class, 'destroy']);
        });

        // Work Teams (فرق العمل)
        Route::middleware('permission:teams.view')->group(function () {
            Route::get('teams', [TeamController::class, 'index']);
            Route::get('teams/{team}', [TeamController::class, 'show']);
        });
        Route::middleware('permission:teams.create')->group(function () {
            Route::post('teams', [TeamController::class, 'store']);
        });
        Route::middleware('permission:teams.edit')->group(function () {
            Route::match(['put', 'patch'], 'teams/{team}', [TeamController::class, 'update']);
        });
        Route::middleware('permission:teams.delete')->group(function () {
            Route::delete('teams/{team}', [TeamController::class, 'destroy']);
        });

        // Bookings
        Route::middleware('permission:bookings.view')->group(function () {
            Route::get('bookings/export', [BookingController::class, 'export']);
            Route::get('bookings', [BookingController::class, 'index']);
            Route::get('bookings/{booking}', [BookingController::class, 'show']);
            Route::get('bookings/{booking}/contract', [BookingContractController::class, 'downloadContractForAdmin']);
        });
        Route::middleware('permission:bookings.edit')->group(function () {
            Route::patch('bookings/{booking}/status', [BookingController::class, 'updateStatus']);
            Route::get('bookings/{booking}/eligible-workers', [BookingController::class, 'eligibleWorkers']);
            Route::post('bookings/{booking}/assign-workers', [BookingController::class, 'assignWorkers']);
            Route::post('bookings/{booking}/replace-worker', [BookingController::class, 'replaceWorker']);
            Route::post('bookings/{booking}/reschedule', [BookingController::class, 'reschedule']);
        });

        // Daily Operations & Exception Management (Document V.18)
        Route::middleware('permission:bookings.view')->group(function () {
            Route::get('operations/daily-dashboard', [DailyOperationsController::class, 'dailyDashboard']);
            Route::get('operations/worker-timeline', [DailyOperationsController::class, 'workerTimeline']);
            Route::get('operations/bookings/{booking}/alternatives', [DailyOperationsController::class, 'getAlternatives']);
            Route::get('operations/absences', [DailyOperationsController::class, 'getAbsences']);
        });
        Route::middleware('permission:bookings.edit')->group(function () {
            Route::post('operations/bookings/{booking}/start', [DailyOperationsController::class, 'startService']);
            Route::post('operations/bookings/{booking}/complete', [DailyOperationsController::class, 'completeService']);
            Route::post('operations/bookings/{booking}/resolve-risk', [DailyOperationsController::class, 'resolveRisk']);
            Route::post('operations/absences', [DailyOperationsController::class, 'recordAbsence']);
            Route::post('operations/absences/{absence}/resolve', [DailyOperationsController::class, 'resolveAbsence']);
        });

        // Home banners (ads) shown in the customer app.
        Route::middleware('permission:banners.view')->group(function () {
            Route::get('ads', [AdsController::class, 'index']);
            Route::get('ads/{ad}', [AdsController::class, 'show']);
        });
        Route::middleware('permission:banners.create')->group(function () {
            Route::post('ads', [AdsController::class, 'store']);
        });
        Route::middleware('permission:banners.edit')->group(function () {
            Route::match(['put', 'patch'], 'ads/{ad}', [AdsController::class, 'update']);
            Route::patch('ads/{ad}/toggle-status', [AdsController::class, 'toggleStatus']);
        });
        Route::middleware('permission:banners.delete')->group(function () {
            Route::delete('ads/{ad}', [AdsController::class, 'destroy']);
        });

        // Announcements shown in the customer app notifications tab.
        Route::middleware('permission:announcements.view')->group(function () {
            Route::get('announcements', [AnnouncementController::class, 'index']);
            Route::get('announcements/{announcement}', [AnnouncementController::class, 'show']);
        });
        Route::middleware('permission:announcements.create')->group(function () {
            Route::post('announcements', [AnnouncementController::class, 'store']);
        });
        Route::middleware('permission:announcements.edit')->group(function () {
            Route::match(['put', 'patch'], 'announcements/{announcement}', [AnnouncementController::class, 'update']);
        });
        Route::middleware('permission:announcements.delete')->group(function () {
            Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy']);
        });

        // Mobile Notifications (Push notifications sent via Firebase)
        Route::middleware('permission:notifications.view')->group(function () {
            Route::get('mobile-notifications', [MobileNotificationController::class, 'index']);
            Route::get('mobile-notifications/{mobile_notification}', [MobileNotificationController::class, 'show']);
        });
        Route::middleware('permission:notifications.create')->group(function () {
            Route::post('mobile-notifications', [MobileNotificationController::class, 'store']);
        });
        Route::middleware('permission:notifications.edit')->group(function () {
            Route::match(['put', 'patch'], 'mobile-notifications/{mobile_notification}', [MobileNotificationController::class, 'update']);
            Route::patch('mobile-notifications/{mobile_notification}/cancel', [MobileNotificationController::class, 'cancel']);
            Route::post('mobile-notifications/{mobile_notification}/send-now', [MobileNotificationController::class, 'sendNow']);
        });
        Route::middleware('permission:notifications.delete')->group(function () {
            Route::delete('mobile-notifications/{mobile_notification}', [MobileNotificationController::class, 'destroy']);
        });

        // Coupons management
        Route::middleware('permission:coupons.view')->group(function () {
            Route::get('coupons', [CouponController::class, 'index']);
            Route::get('coupons/{coupon}', [CouponController::class, 'show']);
        });
        Route::middleware('permission:coupons.create')->group(function () {
            Route::post('coupons', [CouponController::class, 'store']);
        });
        Route::middleware('permission:coupons.edit')->group(function () {
            Route::match(['put', 'patch'], 'coupons/{coupon}', [CouponController::class, 'update']);
        });
        Route::middleware('permission:coupons.delete')->group(function () {
            Route::delete('coupons/{coupon}', [CouponController::class, 'destroy']);
        });

        // Loyalty Points Management
        Route::middleware('permission:points.view')->group(function () {
            Route::get('points/my-quota', [AdminPointsController::class, 'myQuota']);
            Route::get('points/rules', [AdminPointsController::class, 'getRules']);
            Route::get('points/services', [AdminPointsController::class, 'getServices']);
            Route::get('points/transactions', [AdminPointsController::class, 'getTransactions']);
        });
        Route::middleware('permission:points.edit')->group(function () {
            Route::post('points/rules', [AdminPointsController::class, 'storeRule']);
            Route::put('points/rules/{id}', [AdminPointsController::class, 'updateRule']);
            Route::delete('points/rules/{id}', [AdminPointsController::class, 'destroyRule']);
            Route::put('points/services/{id}', [AdminPointsController::class, 'updateServicePoints']);
            Route::post('points/manual-adjustment', [AdminPointsController::class, 'manualAdjustment']);
        });

        // Legal documents
        Route::middleware('permission:legal_documents.view')->group(function () {
            Route::get('legal-documents', [LegalDocumentController::class, 'index']);
            Route::get('legal-documents/{legal_document}', [LegalDocumentController::class, 'show']);
        });
        Route::middleware('permission:legal_documents.create')->group(function () {
            Route::post('legal-documents', [LegalDocumentController::class, 'store']);
        });
        Route::middleware('permission:legal_documents.edit')->group(function () {
            Route::match(['put', 'patch'], 'legal-documents/{legal_document}', [LegalDocumentController::class, 'update']);
        });
        Route::middleware('permission:legal_documents.delete')->group(function () {
            Route::delete('legal-documents/{legal_document}', [LegalDocumentController::class, 'destroy']);
        });

        // Reviews
        Route::middleware('permission:reviews.view')->group(function () {
            Route::get('reviews', [ReviewController::class, 'index']);
        });
        Route::middleware('permission:reviews.edit')->group(function () {
            Route::post('reviews/{review}/approve', [ReviewController::class, 'approve']);
            Route::post('reviews/{review}/reject', [ReviewController::class, 'reject']);
        });

        // Reports
        Route::middleware('permission:reports.view')->group(function () {
            Route::get('reports', [ReportController::class, 'index']);
            Route::get('reports/{report}', [ReportController::class, 'show']);
        });
        Route::middleware('permission:reports.edit')->group(function () {
            Route::post('reports/{report}/assign', [ReportController::class, 'assign']);
            Route::patch('reports/{report}/status', [ReportController::class, 'updateStatus']);
            Route::post('reports/{report}/notes', [ReportController::class, 'addNote']);
        });

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
