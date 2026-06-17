<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\StoreAnnouncementRequest;
use App\Http\Resources\Api\V1\Announcement\AnnouncementResource;
use App\Models\Announcement;
use App\Service\MediaService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AnnouncementController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of the resource.
     */
    public function __construct(protected MediaService $mediaService) {}

    public function index(Request $request)
    {

        $perPage = $request->get('per_page', 15);
        $announcements = Announcement::orderBy('created_at', 'desc')->paginate($perPage);

        return $this->successResponse(AnnouncementResource::collection($announcements), __('messages.announ_fetch_success'));
    }

    /**
     * 2. إنشاء إعلان جديد ورفع الصورة (Store)
     */
    public function store(StoreAnnouncementRequest $request)
    {
        $data = $request->validated();
        $announcement = Announcement::create($data);
        if ($request->hasFile('image')) {
            $this->mediaService->upload($request->file('image'), $announcement, 'announcement/image', 'public');
        }
        Log::info("Admin created a new announcement ID: {$announcement->id}");

        return $this->successResponse(new AnnouncementResource($announcement), __('messages.announcement_create_success'), 201);

    }

    /**
     * 3. عرض تفاصيل إعلان محدد (Show)
     */
    public function show($id)
    {
        $announcement = Announcement::find($id);

        if (! $announcement) {
            return $this->errorResponse(__('messages.annouun_not_exist'), 404);
        }

        return $this->successResponse(new AnnouncementResource($announcement), __('messages.announ_fetch_success'));

    }

    /**
     * 4. تحديث الإعلان وتغيير الصورة (Update)
     */
    public function update(StoreAnnouncementRequest $request, $id)
    {
        $announcement = Announcement::find($id);

        if (! $announcement) {
            return $this->errorResponse(__('messages.annouun_not_exist'), 404);
        }

        $announcement->update($request->validated());

        // معالجة تحديث الصورة
        if ($request->hasFile('image')) {
            // البحث عن الأيقونة القديمة وحذفها
            $oldIcon = $announcement->media()->where('collection', 'announcement/image')->first();
            if ($oldIcon) {
                $this->mediaService->delete($oldIcon);
            }
            // رفع الجديدة
            $this->mediaService->upload($request->file('image'), $announcement, 'announcement/image', 'public');
        }

        Log::info("Admin updated announcement ID: {$id}");

        return $this->successResponse(new AnnouncementResource($announcement), __('messages.announcement_update_success'), 201);
    }

    /**
     * 5. حذف الإعلان وصورته نهائياً (Destroy)
     */
    public function destroy($id)
    {
        $announcement = Announcement::find($id);

        if (! $announcement) {
            return $this->errorResponse(__('messages.annouun_not_exist'), 404);
        }

        $image = $announcement->media()->where('collection', 'announcement/image')->first();
        if ($image) {
            $this->mediaService->delete($image);
        }

        $announcement->delete();

        Log::info("Admin deleted announcement ID: {$id}");

        return $this->successResponse(null, __('messages.announ_delete_success'));

    }
}
