<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreAdRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAdRequest;
use App\Http\Resources\Api\V1\Ad\AdResource;
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

    public function __construct(protected MediaService $mediaService)
    {
    }
    public function index()
    {
        $ads = Ad::query()->where('status','active')->get();
        return $this->successResponse(AdResource::collection($ads),__('messages.ads_fetch_success'));

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
        Log::info("Admin created a new announcement ID: {$ad->id}");
        return $this->successResponse(new AdResource($ad),__('messages.ad_create_success'),201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAdRequest $request, string $id)
    {
        $ad = Ad::find($id);
         if (!$ad) {
            return $this->errorResponse(__('messages.ad_not_exist'),404);
        }
        $ad->update($request->validated());
         if ($request->hasFile('image')) {
                // البحث عن الأيقونة القديمة وحذفها
                $oldIcon = $ad->media()->where('collection', 'ads/image')->first();
                if ($oldIcon) {
                    $this->mediaService->delete($oldIcon);
                }
                // رفع الجديدة
                $this->mediaService->upload($request->file('image'),$ad,'ads/image','public');
            }
        Log::info("Admin updated ad ID: {$id}");
        return $this->successResponse(new AdResource($ad),__('messages.ad_update_success'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $ad = Ad::find($id);

       if (!$ad) {
            return $this->errorResponse(__('messages.ad_not_exist'),404);
        }

        $image = $ad->media()->where('collection','ads/image')->first();
        if($image){
            $this->mediaService->delete($image);
        }

        $ad->delete();

        Log::info("Admin deleted ads ID: {$id}");

        return $this->successResponse(null,__('messages.ad_delete_success'));
    }
}
