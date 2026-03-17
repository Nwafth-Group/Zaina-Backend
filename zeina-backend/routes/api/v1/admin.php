<?php

use App\Http\Controllers\Api\V1\Admin\AreaController;
use App\Http\Controllers\Api\V1\Admin\CategoryController;
use App\Http\Controllers\Api\V1\Admin\CityController;
use App\Http\Controllers\Api\V1\Admin\CountryController;
use App\Http\Controllers\Api\V1\Admin\ProviderController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\ServiceController;
use App\Http\Controllers\Api\V1\Admin\ServicePriceController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Models\Role;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware('auth:sanctum')->group(function (){

Route::prefix('users')->group(function (){
    Route::get("/", [UserController::class, "index"])->middleware('permission:users.view');
    Route::get('/{user}', [UserController::class, 'show'])->middleware('permission:users.view');
    Route::put('/{user}', [UserController::class, 'update'])->middleware('permission:users.edit');
    Route::patch('{user}/toggle-status', [UserController::class, 'toggleStatus'])->middleware('permission:users.edit');
    Route::post('/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('permission:users.edit');
});

Route::apiResource('roles',RoleController::class);
Route::post('roles/{role}/duplicate',[RoleController::class, 'duplicate']); 

 Route::middleware('permission:location.edit')->group( function (){
  Route::apiResource('cities', CityController::class);
  Route::post('cities/{city}/toggle-status', [CityController::class, 'toggleStatus']);

 Route::apiResource('countries', CountryController::class);
 Route::post('countries/{country}/toggle-status', [CountryController::class, 'toggleStatus']);

  Route::apiResource('areas', AreaController::class);
  Route::post('areas/{area}/toggle-status', [AreaController::class, 'toggleStatus']);
});

 Route::apiResource('categories', CategoryController::class);
 Route::apiResource('services',ServiceController::class);
 Route::post('services/{service}/toggle-status', [ServiceController::class, 'toggleStatus']);
 Route::apiResource('servicesprices',ServicePriceController::class);
 Route::post('servicesprices/estimated',[ServicePriceController::class,'getEstimatedPrice']);


 Route::middleware('permission:providers.view')->prefix('providers')->group(function () {
        Route::get('/', [ProviderController::class, 'index']);
        Route::post('/', [ProviderController::class, 'store'])->middleware('permission:providers.create');
        Route::put('/{provider}', [ProviderController::class, 'update'])->middleware('permission:providers.edit');
        Route::patch('/{provider}/toggle-status', [ProviderController::class,'toggleStatus'])->middleware('permission:providers.deactivate');
        // US-02: Mapping Route
        Route::post('/{provider}/map', [ProviderController::class, 'mapProvider'])->middleware('permission:provider_eligibility.edit');
    });



 

});