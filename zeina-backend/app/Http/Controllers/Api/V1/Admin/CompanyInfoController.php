<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateCompanyInfoRequest;
use App\Http\Resources\Api\V1\CompanyInfo\CompanyInfoResource;
use App\Models\CompanyInfo;
use App\Service\MediaService;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Log;

class CompanyInfoController extends Controller
{
    use ApiResponseTrait;

    public function __construct(protected MediaService $mediaService) {}

    /**
     * Display the company information.
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

    /**
     * Update the company information.
     */
    public function update(UpdateCompanyInfoRequest $request)
    {
        $info = CompanyInfo::firstOrCreate(
            ['id' => 1],
            [
                'name_ar' => 'زينا',
                'name_en' => 'Zeina',
                'vat_percentage' => 0.00,
            ]
        );

        $info->update($request->validated());

        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            $oldLogo = $info->media()->where('collection', 'company_info/logo')->first();
            if ($oldLogo) {
                $this->mediaService->delete($oldLogo);
            }
            // Upload new logo
            $this->mediaService->upload($request->file('logo'), $info, 'company_info/logo', 'public');
        }

        Log::info('Admin updated company settings');

        return $this->successResponse(new CompanyInfoResource($info->load('media')), 'Company settings updated successfully.');
    }
}
