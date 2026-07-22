<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Governrate\GovernrateResource;
use App\Models\Govrenrate;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GovrenrateController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of governorates.
     */
    public function index(Request $request)
    {
        $query = Govrenrate::query()->with('country');

        if ($request->filled('country_id')) {
            $query->where('country_id', $request->input('country_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name_ar', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('all')) {
            $governorates = $query->orderBy('name_en')->get();
            return $this->successResponse([
                'items' => GovernrateResource::collection($governorates),
            ], __('messages.governorates_fetch_success') ?? 'Governorates retrieved successfully.');
        }

        $perPage = (int) $request->input('per_page', 15);
        $governorates = $query->latest()->paginate($perPage);

        return $this->successResponse([
            'items' => GovernrateResource::collection($governorates->items()),
            'meta' => [
                'current_page' => $governorates->currentPage(),
                'last_page' => $governorates->lastPage(),
                'per_page' => $governorates->perPage(),
                'total' => $governorates->total(),
            ],
        ], __('messages.governorates_fetch_success') ?? 'Governorates retrieved successfully.');
    }

    /**
     * Store a newly created governorate.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $governorate = Govrenrate::create([
            'country_id' => $validated['country_id'],
            'name_ar' => $validated['name_ar'],
            'name_en' => $validated['name_en'],
            'status' => $validated['status'] ?? 'active',
        ]);

        $governorate->load('country');

        return $this->successResponse(
            new GovernrateResource($governorate),
            __('messages.governorate_created_successfully') ?? 'Governorate created successfully.',
            201
        );
    }

    /**
     * Display the specified governorate.
     */
    public function show(Govrenrate $govrenrate)
    {
        $govrenrate->load('country');

        return $this->successResponse(
            new GovernrateResource($govrenrate),
            __('messages.governorate_retrieved_successfully') ?? 'Governorate retrieved successfully.'
        );
    }

    /**
     * Update the specified governorate.
     */
    public function update(Request $request, Govrenrate $govrenrate)
    {
        $validated = $request->validate([
            'country_id' => ['sometimes', 'required', 'integer', 'exists:countries,id'],
            'name_ar' => ['sometimes', 'required', 'string', 'max:255'],
            'name_en' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $govrenrate->update($validated);
        $govrenrate->load('country');

        return $this->successResponse(
            new GovernrateResource($govrenrate),
            __('messages.governorate_updated_successfully') ?? 'Governorate updated successfully.'
        );
    }

    /**
     * Remove the specified governorate.
     */
    public function destroy(Govrenrate $govrenrate)
    {
        if ($govrenrate->cities()->exists()) {
            return $this->errorResponse(
                __('messages.cannot_delete_governorate_with_cities') ?? 'Cannot delete governorate that has linked cities.',
                422
            );
        }

        $govrenrate->delete();

        return $this->successResponse(null, __('messages.governorate_deleted_successfully') ?? 'Governorate deleted successfully.');
    }

    /**
     * Toggle status between active and inactive.
     */
    public function toggleStatus(Govrenrate $govrenrate)
    {
        $newStatus = $govrenrate->status === 'active' ? 'inactive' : 'active';
        $govrenrate->update(['status' => $newStatus]);
        $govrenrate->load('country');

        return $this->successResponse(
            new GovernrateResource($govrenrate),
            __('messages.governorate_status_toggled_successfully') ?? 'Governorate status toggled successfully.'
        );
    }
}
