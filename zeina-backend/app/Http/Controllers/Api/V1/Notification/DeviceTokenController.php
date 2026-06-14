<?php

namespace App\Http\Controllers\Api\V1\Notification;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeviceTokenController extends Controller
{ 
    use ApiResponseTrait;
    public function updateToken(Request $request)
    {
        $request->validate([
            'fcm_token'   => 'required|string',
            'device_type' => 'nullable|string|in:ios,android,web',
        ]);

        
        DB::table('user_devices')->updateOrInsert(
            ['fcm_token' => $request->fcm_token], // شرط الفحص (التوكن فريد للجهاز)
            [
                'user_id'     => auth()->user()->id,
                'device_type' => $request->device_type,
                'updated_at'  => now(),
                'created_at'  => now(), // لارافيل ذكي سيستخدمها فقط في حال الـ Insert
            ]
        );

        return $this->successResponse(null, 'Device FCM Token updated successfully.');
    }
}
