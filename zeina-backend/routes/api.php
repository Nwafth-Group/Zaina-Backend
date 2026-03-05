<?php

use App\Http\Controllers\Api\V1\Customer\TestController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(base_path('routes/api/v1/customer.php')); 

Route::prefix('v1')->group(base_path('routes/api/v1/admin.php'));

// Route::get('v1/test',[TestController::class,'test']);
