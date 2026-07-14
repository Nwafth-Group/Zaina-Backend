<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CompanyInfo\CompanyInfoResource;
use App\Models\CompanyInfo;
use App\Traits\ApiResponseTrait;

class CompanyInfoController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display the company information for customers.
     */
    public function show()
    {
        $info = CompanyInfo::with('media')->firstOrCreate(
            ['id' => 1],
            [
                'name_ar' => 'زينا',
                'name_en' => 'Zeina',
                'vat_percentage' => 0.00,
            ]
        );

        return $this->successResponse(new CompanyInfoResource($info), 'Company information retrieved successfully.');
    }
}
