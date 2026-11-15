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
use App\Http\Controllers\Api\V1\Admin\CompanyInfoController;
use App\Http\Controllers\Api\V1\Admin\CouponController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\EmployeeController;
use App\Http\Controllers\Api\V1\Admin\LegalDocumentController;
use App\Http\Controllers\Api\V1\Admin\MobileNotificationController;
use App\Http\Controllers\Api\V1\Admin\ProfileController;
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

    // =========================================================================
    // 1. AUTHENTICATION & PUBLIC
    // =========================================================================
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        Route::post('login', 'login');
        Route::post('logout', 'logout')->middleware('auth:sanctum');
    });

    Route::get('kyc/download/{media}', [UserController::class, 'downloadKycDoc'])
        ->name('admin.kyc.download')
        ->middleware('signed');

    // =========================================================================
    // 2. AUTHENTICATED ADMIN ROUTES
    // =========================================================================
    Route::middleware(['auth:sanctum', IsAdmin::class])->group(function () {

        // ---------------------------------------------------------
        // Dashboard & Settings
        // ---------------------------------------------------------
        Route::get('dashboard/stats', [DashboardController::class, 'stats']);

        Route::controller(CompanyInfoController::class)->prefix('company-info')->group(function () {
            Route::get('/', 'show');
            Route::post('/', 'update');
        });

        Route::controller(ProfileController::class)->group(function () {
            Route::get('me', 'me');
            Route::get('me/notifications', 'notifications');
            Route::get('me/notifications/stream', 'notificationStream');
            Route::post('me/notifications/read', 'markNotificationsAsRead');

            Route::get('profile', 'show');
            Route::put('profile', 'update');
            Route::put('profile/password', 'updatePassword');
        });

        // ---------------------------------------------------------
        // Users & HR Management
        // ---------------------------------------------------------
        Route::prefix('users')->controller(UserController::class)->group(function () {
            Route::get('/', 'index')->middleware('permission:users.view');
            Route::get('/{user}', 'show')->middleware('permission:users.view');
            Route::put('/{user}', 'update')->middleware('permission:users.edit');
            Route::match(['post', 'patch'], '/{user}/toggle-status', 'toggleStatus')->middleware('permission:users.edit');
            Route::post('/{user}/reset-password', 'resetPassword')->middleware('permission:users.edit');
            Route::match(['post', 'put'], '/{user}/verify', 'verifyUser');
        });

        Route::prefix('employees')->controller(EmployeeController::class)->group(function () {
            Route::get('/', 'index')->middleware('permission:employees.view');
            Route::post('/', 'store')->middleware('permission:employees.create');
            Route::get('/{employee}', 'show')->middleware('permission:employees.view');
            Route::match(['put', 'patch'], '/{employee}', 'update')->middleware('permission:employees.edit');
            Route::match(['post', 'patch'], '/{employee}/toggle-status', 'toggleStatus')->middleware('permission:employees.edit');
            Route::delete('/{employee}', 'destroy')->middleware('permission:employees.delete');
        });

        Route::prefix('roles')->controller(RoleController::class)->group(function () {
            Route::get('/', 'index')->middleware('permission:roles.view');
            Route::post('/', 'store')->middleware('permission:roles.create');
            Route::get('/permissions', 'permissions')->middleware('permission:roles.view');
            Route::get('/{role}', 'show')->middleware('permission:roles.view');
            Route::match(['put', 'patch'], '/{role}', 'update')->middleware('permission:roles.edit');
            Route::delete('/{role}', 'destroy')->middleware('permission:roles.delete');
            Route::post('/{role}/duplicate', 'duplicate')->middleware('permission:roles.create');
        });

        Route::prefix('workers')->controller(WorkerController::class)->group(function () {
            Route::get('/', 'index')->middleware('permission:workers.view');
            Route::get('/nationalities', 'nationalities')->middleware('permission:workers.view');
            Route::post('/', 'store')->middleware('permission:workers.create');
            Route::get('/{worker}', 'show')->middleware('permission:workers.view');
            Route::get('/{worker}/details', 'details')->middleware('permission:workers.view');
            Route::match(['put', 'patch'], '/{worker}', 'update')->middleware('permission:workers.edit');
            Route::match(['post', 'patch'], '/{worker}/toggle-status', 'changeStatus')->middleware('permission:workers.edit');
            Route::delete('/{worker}', 'destroy')->middleware('permission:workers.delete');
        });

        Route::prefix('teams')->controller(TeamController::class)->group(function () {
            Route::get('/', 'index')->middleware('permission:teams.view');
            Route::post('/', 'store')->middleware('permission:teams.create');
            Route::get('/{team}', 'show')->middleware('permission:teams.view');
            Route::match(['put', 'patch'], '/{team}', 'update')->middleware('permission:teams.edit');
            Route::delete('/{team}', 'destroy')->middleware('permission:teams.delete');
        });

        // ---------------------------------------------------------
        // Catalog (Categories & Services)
        // ---------------------------------------------------------
        Route::prefix('categories')->controller(CategoryController::class)->group(function () {
            Route::get('/', 'index')->middleware('permission:categories.view');
            Route::post('/', 'store')->middleware('permission:categories.create');
            Route::get('/{category}', 'show')->middleware('permission:categories.view');
            Route::match(['put', 'patch'], '/{category}', 'update')->middleware('permission:categories.edit');
            Route::match(['post', 'patch'], '/{category}/toggle-status', 'toggleStatus')->middleware('permission:categories.edit');
            Route::delete('/{category}', 'destroy')->middleware('permission:categories.delete');
        });

        Route::prefix('services')->controller(ServiceController::class)->group(function () {
            Route::get('/', 'index')->middleware('permission:services.view');
            Route::post('/', 'store')->middleware('permission:services.create');
            Route::get('/{service}', 'show')->middleware('permission:services.view');
            Route::get('/{service}/details', 'details')->middleware('permission:services.view');
            Route::match(['put', 'patch'], '/{service}', 'update')->middleware('permission:services.edit');
            Route::match(['post', 'patch'], '/{service}/toggle-status', 'toggleStatus')->middleware('permission:services.edit');
            Route::delete('/{service}', 'destroy')->middleware('permission:services.delete');
        });

        // ---------------------------------------------------------
        // Logistics & Fleet
        // ---------------------------------------------------------
        Route::prefix('drivers')->controller(DriverController::class)->group(function () {
            Route::get('/', 'index')->middleware('permission:drivers.view');
            Route::post('/', 'store')->middleware('permission:drivers.create');
            Route::get('/{driver}', 'show')->middleware('permission:drivers.view');
            Route::match(['put', 'patch'], '/{driver}', 'update')->middleware('permission:drivers.edit');
            Route::delete('/{driver}', 'destroy')->middleware('permission:drivers.delete');
        });

        Route::prefix('departure-points')->controller(DeparturePointController::class)->group(function () {
            Route::get('/', 'index')->middleware('permission:departure_points.view');
            Route::post('/', 'store')->middleware('permission:departure_points.create');
            Route::get('/{departure_point}', 'show')->middleware('permission:departure_points.view');
            Route::match(['put', 'patch'], '/{departure_point}', 'update')->middleware('permission:departure_points.edit');
            Route::delete('/{departure_point}', 'destroy')->middleware('permission:departure_points.delete');
        });

        Route::prefix('service-zones')->controller(ServiceZoneController::class)->group(function () {
            Route::get('/', 'index')->middleware('permission:service_zones.view');
            Route::post('/', 'store')->middleware('permission:service_zones.create');
            Route::post('/check-point', 'checkPoint')->middleware('permission:service_zones.view');
            Route::get('/{service_zone}', 'show')->middleware('permission:service_zones.view');
            Route::match(['put', 'patch'], '/{service_zone}', 'update')->middleware('permission:service_zones.edit');
            Route::delete('/{service_zone}', 'destroy')->middleware('permission:service_zones.delete');
        });

        Route::prefix('transport-dispatch')->controller(TransportDispatchController::class)->group(function () {
            // New Shift-Based Endpoints
            Route::get('/shifts-overview', 'shiftsOverview')->middleware('permission:transport_dispatch.view');
            Route::get('/shift-details', 'shiftDetails')->middleware('permission:transport_dispatch.view');
            Route::post('/assign-worker', 'assignWorker')->middleware('permission:transport_dispatch.edit');
            Route::post('/unassign-worker', 'unassignWorker')->middleware('permission:transport_dispatch.edit');
            Route::get('/drivers/{id}/export-excel', 'exportDriverManifest')->middleware('permission:transport_dispatch.view');
            Route::get('/shifts/export-excel', 'exportShiftManifest')->middleware('permission:transport_dispatch.view');

            // Legacy / Region-Based Endpoints
            Route::get('/daily-overview', 'dailyOverview')->middleware('permission:transport_dispatch.view');
            Route::get('/region-details', 'regionGroupDetails')->middleware('permission:transport_dispatch.view');
            Route::get('/groups/{id}/export-excel', 'exportGroupExcel')->middleware('permission:transport_dispatch.view');
            Route::put('/groups/{id}', 'updateGroupDispatch')->middleware('permission:transport_dispatch.edit');
        });

        // ---------------------------------------------------------
        // Operations & Bookings
        // ---------------------------------------------------------
        Route::prefix('bookings')->group(function () {
            Route::controller(BookingController::class)->group(function () {
                Route::get('/', 'index')->middleware('permission:bookings.view');
                Route::get('/export', 'export')->middleware('permission:bookings.view');
                Route::get('/{booking}', 'show')->middleware('permission:bookings.view');
                Route::patch('/{booking}/status', 'updateStatus')->middleware('permission:bookings.edit');
                Route::get('/{booking}/eligible-workers', 'eligibleWorkers')->middleware('permission:bookings.edit');
                Route::post('/{booking}/assign-workers', 'assignWorkers')->middleware('permission:bookings.edit');
                Route::post('/{booking}/replace-worker', 'replaceWorker')->middleware('permission:bookings.edit');
                Route::get('/{booking}/eligible-teams', 'eligibleTeams')->middleware('permission:bookings.edit');
                Route::post('/{booking}/assign-team', 'assignTeam')->middleware('permission:bookings.edit');
                Route::post('/{booking}/reschedule', 'reschedule')->middleware('permission:bookings.edit');
                Route::get('/{booking}/reschedule-shifts', 'rescheduleShifts')->middleware('permission:bookings.view');
            });
            Route::get('/{booking}/contract', [BookingContractController::class, 'downloadContractForAdmin'])->middleware('permission:bookings.view');
        });

        Route::prefix('operations')->controller(DailyOperationsController::class)->group(function () {
            Route::get('/daily-dashboard', 'dailyDashboard')->middleware('permission:bookings.view');
            Route::get('/worker-timeline', 'workerTimeline')->middleware('permission:bookings.view');

            Route::get('/absences', 'getAbsences')->middleware('permission:bookings.view');
            Route::post('/absences', 'recordAbsence')->middleware('permission:bookings.edit');
            Route::post('/absences/{absence}/resolve', 'resolveAbsence')->middleware('permission:bookings.edit');

            Route::get('/bookings/{booking}/alternatives', 'getAlternatives')->middleware('permission:bookings.view');
            Route::post('/bookings/{booking}/start', 'startService')->middleware('permission:bookings.edit');
            Route::post('/bookings/{booking}/complete', 'completeService')->middleware('permission:bookings.edit');
            Route::post('/bookings/{booking}/resolve-risk', 'resolveRisk')->middleware('permission:bookings.edit');
        });

        // ---------------------------------------------------------
        // Marketing & Loyalty
        // ---------------------------------------------------------
        Route::prefix('ads')->controller(AdsController::class)->group(function () {
            Route::get('/', 'index')->middleware('permission:banners.view');
            Route::post('/', 'store')->middleware('permission:banners.create');
            Route::get('/{ad}', 'show')->middleware('permission:banners.view');
            Route::match(['put', 'patch'], '/{ad}', 'update')->middleware('permission:banners.edit');
            Route::match(['post', 'patch'], '/{ad}/toggle-status', 'toggleStatus')->middleware('permission:banners.edit');
            Route::delete('/{ad}', 'destroy')->middleware('permission:banners.delete');
        });

        Route::prefix('announcements')->controller(AnnouncementController::class)->group(function () {
            Route::get('/', 'index')->middleware('permission:announcements.view');
            Route::post('/', 'store')->middleware('permission:announcements.create');
            Route::get('/{announcement}', 'show')->middleware('permission:announcements.view');
            Route::match(['put', 'patch'], '/{announcement}', 'update')->middleware('permission:announcements.edit');
            Route::delete('/{announcement}', 'destroy')->middleware('permission:announcements.delete');
        });

        Route::prefix('mobile-notifications')->controller(MobileNotificationController::class)->group(function () {
            Route::get('/', 'index')->middleware('permission:notifications.view');
            Route::post('/', 'store')->middleware('permission:notifications.create');
            Route::get('/{mobile_notification}', 'show')->middleware('permission:notifications.view');
            Route::match(['put', 'patch'], '/{mobile_notification}', 'update')->middleware('permission:notifications.edit');
            Route::patch('/{mobile_notification}/cancel', 'cancel')->middleware('permission:notifications.edit');
            Route::post('/{mobile_notification}/send-now', 'sendNow')->middleware('permission:notifications.edit');
            Route::delete('/{mobile_notification}', 'destroy')->middleware('permission:notifications.delete');
        });

        Route::prefix('coupons')->controller(CouponController::class)->group(function () {
            Route::get('/', 'index')->middleware('permission:coupons.view');
            Route::post('/', 'store')->middleware('permission:coupons.create');
            Route::get('/{coupon}', 'show')->middleware('permission:coupons.view');
            Route::match(['put', 'patch'], '/{coupon}', 'update')->middleware('permission:coupons.edit');
            Route::delete('/{coupon}', 'destroy')->middleware('permission:coupons.delete');
        });

        Route::prefix('points')->controller(AdminPointsController::class)->group(function () {
            Route::get('/my-quota', 'myQuota')->middleware('permission:points.view');
            Route::get('/rules', 'getRules')->middleware('permission:points.view');
            Route::post('/rules', 'storeRule')->middleware('permission:points.edit');
            Route::put('/rules/{id}', 'updateRule')->middleware('permission:points.edit');
            Route::delete('/rules/{id}', 'destroyRule')->middleware('permission:points.edit');
            Route::get('/services', 'getServices')->middleware('permission:points.view');
            Route::put('/services/{id}', 'updateServicePoints')->middleware('permission:points.edit');
            Route::get('/transactions', 'getTransactions')->middleware('permission:points.view');
            Route::post('/manual-adjustment', 'manualAdjustment')->middleware('permission:points.edit');
        });

        // ---------------------------------------------------------
        // System & Documents
        // ---------------------------------------------------------
        Route::prefix('legal-documents')->controller(LegalDocumentController::class)->group(function () {
            Route::get('/', 'index')->middleware('permission:legal_documents.view');
            Route::post('/', 'store')->middleware('permission:legal_documents.create');
            Route::get('/{legal_document}', 'show')->middleware('permission:legal_documents.view');
            Route::match(['put', 'patch'], '/{legal_document}', 'update')->middleware('permission:legal_documents.edit');
            Route::delete('/{legal_document}', 'destroy')->middleware('permission:legal_documents.delete');
        });

        Route::prefix('reviews')->controller(ReviewController::class)->group(function () {
            Route::get('/', 'index')->middleware('permission:reviews.view');
            Route::post('/{review}/approve', 'approve')->middleware('permission:reviews.edit');
            Route::post('/{review}/reject', 'reject')->middleware('permission:reviews.edit');
        });

        Route::prefix('reports')->controller(ReportController::class)->group(function () {
            Route::get('/', 'index')->middleware('permission:reports.view');
            Route::get('/assignable-employees', 'assignableEmployees')->middleware('permission:reports.view');
            Route::get('/{report}', 'show')->middleware('permission:reports.view');
            Route::post('/{report}/assign', 'assign')->middleware('permission:reports.edit');
            Route::patch('/{report}/status', 'updateStatus')->middleware('permission:reports.edit');
            Route::post('/{report}/notes', 'addNote')->middleware('permission:reports.edit');
        });

    });
});