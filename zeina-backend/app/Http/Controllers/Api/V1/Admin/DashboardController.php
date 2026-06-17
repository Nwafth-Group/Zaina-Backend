<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Service\Admin\DashboardService;
use App\Traits\ApiResponseTrait;

class DashboardController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly DashboardService $dashboard) {}

    /**
     * Aggregated statistics for the admin dashboard. All computation lives in
     * the DashboardService; the controller only delegates and wraps the result.
     */
    public function stats()
    {
        return $this->successResponse($this->dashboard->stats(), __('messages.dashboard_retrieved'));
    }
}
