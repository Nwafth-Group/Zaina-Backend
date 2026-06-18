<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\StoreAnnouncementRequest;
use App\Http\Resources\Api\V1\Announcement\AnnouncementResource;
use App\Models\Announcement;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AnnouncementController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request)
    {
        // Admin management view: list every announcement, newest first.
        $perPage = $request->integer('per_page', 12);
        $announcements = Announcement::query()->withCount('readers')->latest()->paginate($perPage);

        return $this->successResponse(AnnouncementResource::collection($announcements), __('messages.announ_fetch_success'));
    }

    /**
     * Create an announcement and deliver it to the selected customers.
     */
    public function store(StoreAnnouncementRequest $request)
    {
        $announcement = Announcement::create($request->safe()->only(['title', 'body']));

        $this->syncRecipients($announcement, $request);

        Log::info("Admin created a new announcement ID: {$announcement->id}");

        return $this->successResponse(new AnnouncementResource($announcement->load('readers')), __('messages.announcement_create_success'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $announcement = Announcement::with('readers')->find($id);

        if (! $announcement) {
            return $this->errorResponse(__('messages.annouun_not_exist'), 404);
        }

        return $this->successResponse(new AnnouncementResource($announcement), __('messages.announ_fetch_success'));
    }

    /**
     * Update the announcement content and re-sync its readers.
     */
    public function update(StoreAnnouncementRequest $request, $id)
    {
        $announcement = Announcement::find($id);

        if (! $announcement) {
            return $this->errorResponse(__('messages.annouun_not_exist'), 404);
        }

        $announcement->update($request->safe()->only(['title', 'body']));

        $this->syncRecipients($announcement, $request);

        Log::info("Admin updated announcement ID: {$id}");

        return $this->successResponse(new AnnouncementResource($announcement->load('readers')), __('messages.announcement_update_success'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $announcement = Announcement::find($id);

        if (! $announcement) {
            return $this->errorResponse(__('messages.annouun_not_exist'), 404);
        }

        $announcement->delete();

        Log::info("Admin deleted announcement ID: {$id}");

        return $this->successResponse(null, __('messages.announ_delete_success'));
    }

    /**
     * Resolve the reader list (all customers or a chosen subset) and sync it
     * onto the announcement. Existing readers' read state is preserved.
     */
    private function syncRecipients(Announcement $announcement, StoreAnnouncementRequest $request): void
    {
        $ids = $request->boolean('send_to_all')
            ? User::query()->where('type', 'customer')->pluck('id')->all()
            : array_map('intval', $request->input('customer_ids', []));

        $announcement->readers()->sync($ids);
    }
}
