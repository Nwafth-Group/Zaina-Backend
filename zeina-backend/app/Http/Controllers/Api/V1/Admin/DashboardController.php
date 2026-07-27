<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Service\Admin\DashboardService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly DashboardService $dashboard) {}

    /**
     * Aggregated statistics for the admin dashboard. All computation lives in
     * the DashboardService; the controller only delegates and wraps the result.
     */
    public function stats(Request $request)
    {
        $governorate = $request->input('governorate');
        $serviceZoneId = $request->input('service_zone_id') ? (int) $request->input('service_zone_id') : null;

        return $this->successResponse(
            $this->dashboard->stats($governorate, $serviceZoneId),
            __('messages.dashboard_retrieved')
        );
    }
}
