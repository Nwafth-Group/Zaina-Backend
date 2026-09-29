<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(base_path('routes' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'V1' . DIRECTORY_SEPARATOR . 'customer.php')); 

Route::prefix('v1')->group(base_path('routes' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'V1' . DIRECTORY_SEPARATOR . 'admin.php'));