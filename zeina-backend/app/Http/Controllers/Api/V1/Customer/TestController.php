<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function test(){
        return response()->json([
            'data'=>null,
            'message'=>__('messages.account_deleted')
             
        ]);
    }
}
