<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Ad\AdResource;
use App\Http\Resources\Api\V1\Media\MediaResource;
use App\Models\Ad;
use App\Models\Booking;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // 1. Banners
        $banners = Ad::where('status', 'active')
            ->with(['media'])
            ->get();

        $currentDate = now()->toDateString();
        $currentTime = now()->toTimeString();

        // 2. Upcoming Appointment
        $upcomingBooking = null;

        if (auth('sanctum')->check()) {
            $user = auth('sanctum')->user();
            $booking = Booking::with(['service.media'])
                ->where('customer_id', $user->id)
                ->whereIn('status', ['Upcoming', 'inService'])
                ->where(function ($query) use ($currentDate, $currentTime) {
                    $query->where('booking_date', '>', $currentDate)
                        ->orWhere(function ($q) use ($currentDate, $currentTime) {
                            $q->where('booking_date', '=', $currentDate)
                                ->where('start_time', '>=', $currentTime);
                        });
                })
                ->orderBy('booking_date', 'asc')
                ->orderBy('start_time', 'asc')
                ->first();

            if ($booking) {
                // Calculate duration
                $hours = floor($booking->duration_minutes / 60);
                $durationText = $hours > 0 ? "{$hours} Hours" : "{$booking->duration_minutes} Mins";
                $mediaService = $booking->service->media()->where('collection', 'service/icon')->first();

                $upcomingBooking = [
                    'id' => $booking->id,
                    'booking_ref' => $booking->booking_ref,
                    'service_name_ar' => $booking->service->name_ar,
                    'service_name_en' => $booking->service->name_en,
                    'image' => $mediaService ? (new MediaResource($mediaService))->getFileUrl() : null,
                    'price' => $booking->total_amount,
                    'currency' => 'EGP',
                    'status' => $booking->status,
                    'duration_text' => $durationText,
                    'date_day' => Carbon::parse($booking->booking_date)->format('d'),
                    'date_month' => Carbon::parse($booking->booking_date)->format('M'),
                    'time' => Carbon::parse($booking->start_time)->format('h:i A'),
                ];
            }
        }

        // Return only banners and upcoming_appointment
        return $this->successResponse([
            'banners' => AdResource::collection($banners),
            'upcoming_appointment' => $upcomingBooking ?? null,
        ], 'Home page data fetched successfully.');
    }
}
