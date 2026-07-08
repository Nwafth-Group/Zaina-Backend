<?php

use App\Http\Controllers\Api\V1\Customer\TestController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(base_path('routes' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'V1' . DIRECTORY_SEPARATOR . 'customer.php')); 

Route::prefix('v1')->group(base_path('routes' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'V1' . DIRECTORY_SEPARATOR . 'admin.php'));

// Route::get('v1/test',[TestController::class,'test']);
