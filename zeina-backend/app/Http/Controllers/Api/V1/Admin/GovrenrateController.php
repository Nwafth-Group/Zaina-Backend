<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Governrate\GovernrateResource;
use App\Models\Govrenrate;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class GovrenrateController extends Controller
{
    use ApiResponseTrait;

    /**
     * Read-only governorate lookup that feeds the City form's parent selector.
     * Optionally scoped to a country.
     */
    public function index(Request $request)
    {
        $query = Govrenrate::query()->with('country')->orderBy('name_en');

        if ($request->filled('country_id')) {
            $query->where('country_id', $request->input('country_id'));
        }

        return $this->successResponse(GovernrateResource::collection($query->get()), __('messages.governorates_fetch_success'));
    }
}
