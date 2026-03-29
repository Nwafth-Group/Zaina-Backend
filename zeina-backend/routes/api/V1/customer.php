<?php

use App\Http\Controllers\Api\V1\Customer\AddressController;
use App\Http\Controllers\Api\V1\Customer\AuthController;
use App\Http\Controllers\Api\V1\Customer\ProfileController;
use App\Http\Controllers\Api\V1\Customer\TestController;
use Illuminate\Support\Facades\Route;

Route::prefix('customer')->group(function(){

Route::get('test',[TestController::class,'test']);

Route::prefix('auth')->group(function(){
    // Route::post('/register',[AuthController::class,'register']);
    // Route::post('/verify-register',[AuthController::class,'verifyRegister']);
    Route::post('/send-otp',[AuthController::class,'sendotp']);
    Route::post('/verify-otp',[AuthController::class,'verifyOtp']);
    Route::post('/logout',[AuthController::class,'logout'])->middleware('auth:sanctum');
});


Route::prefix('profile')->middleware('auth:sanctum')->group(function (){
    Route::post("complete", [ProfileController::class, 'completeProfile']);
   Route::post('/send-delete-otp', [ProfileController::class, 'sendDeleteOtp']);
    Route::post('/delete', [ProfileController::class, 'deleteAccount']);  
});

Route::apiResource('addresses',AddressController::class)->middleware('auth:sanctum');
Route::post('address/{id}/set-default',[AddressController::class,'setDefault'])->middleware('auth:sanctum');
    
});
