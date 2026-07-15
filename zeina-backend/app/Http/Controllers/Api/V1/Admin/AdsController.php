<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreAdRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAdRequest;
use App\Http\Resources\Api\V1\Ad\AdminAdResource;
use App\Models\Ad;
use App\Service\MediaService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdsController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of the resource.
     */
    public function __construct(protected MediaService $mediaService) {}

    public function index(Request $request)
    {
        $query = Ad::query()->with('media');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title_en', 'like', "%{$search}%")
                    ->orWhere('title_ar', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Sorting
        $allowedSorts = ['status', 'created_at', 'id'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true) ? $request->input('sort_by') : 'created_at';
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        $perPage = $request->integer('per_page', 12);
        $ads = $query->paginate($perPage)->withQueryString();

        return $this->successResponse(AdminAdResource::collection($ads), __('messages.ads_fetch_success'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAdRequest $request)
    {
        $data = $request->validated();
        $ad = Ad::create($data);
        if ($request->hasFile('image')) {
            $this->mediaService->upload($request->file('image'), $ad, 'ads/image', 'public');
        }
        Log::info("Admin created a new banner ID: {$ad->id}");

        return $this->successResponse(new AdminAdResource($ad->load('media')), __('messages.ad_create_success'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $ad = Ad::with('media')->find($id);
        if (! $ad) {
            return $this->errorResponse(__('messages.ad_not_exist'), 404);
        }

        return $this->successResponse(new AdminAdResource($ad), __('messages.ads_fetch_success'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAdRequest $request, string $id)
    {
        $ad = Ad::find($id);
        if (! $ad) {
            return $this->errorResponse(__('messages.ad_not_exist'), 404);
        }
        $ad->update($request->validated());
        if ($request->hasFile('image')) {
            // البحث عن الأيقونة القديمة وحذفها
            $oldIcon = $ad->media()->where('collection', 'ads/image')->first();
            if ($oldIcon) {
                $this->mediaService->delete($oldIcon);
            }
            // رفع الجديدة
            $this->mediaService->upload($request->file('image'), $ad, 'ads/image', 'public');
        }
        Log::info("Admin updated banner ID: {$id}");

        return $this->successResponse(new AdminAdResource($ad->load('media')), __('messages.ad_update_success'));
    }

    /**
     * Toggle a banner between active and inactive.
     */
    public function toggleStatus(string $id)
    {
        $ad = Ad::find($id);
        if (! $ad) {
            return $this->errorResponse(__('messages.ad_not_exist'), 404);
        }

        $ad->status = $ad->status === 'active' ? 'inactive' : 'active';
        $ad->save();

        return $this->successResponse(new AdminAdResource($ad->load('media')), __('messages.ad_toggle_success', ['status' => $ad->status]));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $ad = Ad::find($id);

        if (! $ad) {
            return $this->errorResponse(__('messages.ad_not_exist'), 404);
        }

        $image = $ad->media()->where('collection', 'ads/image')->first();
        if ($image) {
            $this->mediaService->delete($image);
        }

        $ad->delete();

        Log::info("Admin deleted ads ID: {$id}");

        return $this->successResponse(null, __('messages.ad_delete_success'));
    }
}
