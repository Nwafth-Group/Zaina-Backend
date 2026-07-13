<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LegalDocument\LegalDocumentResource;
use App\Service\LegalService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;



class LegalDocumentController extends Controller
{
    use ApiResponseTrait;
    /**
     * Display a listing of the resource.
     */

    public function __construct(protected LegalService $legalService)
    {
    }

    public function getTerms()
    {
        $documents = $this->legalService->getDocumentsByType('terms');
        
        return $this->successResponse(
            LegalDocumentResource::collection($documents),
            'Terms and conditions retrieved successfully.'
        );
    }

    /**
     * شاشة سياسة الخصوصية (Privacy Policy)
     */
    public function getPrivacy()
    {
        $documents = $this->legalService->getDocumentsByType('privacy');
        
        return $this->successResponse(
            LegalDocumentResource::collection($documents),
            'Privacy policy retrieved successfully.'
        );
    }

    /**
     * شاشة من نحن (About Us)
     */
    public function getAboutUs()
    {
        $documents = $this->legalService->getDocumentsByType('about_us');
        
        return $this->successResponse(
            LegalDocumentResource::collection($documents),
            'About us retrieved successfully.'
        );
    }


   
}
