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

        // 2. Upcoming Appointments (up to 5 closest)
        $upcomingBookings = [];

        if (auth('sanctum')->check()) {
            $user = auth('sanctum')->user();
            $bookings = Booking::with(['service.media', 'service.rules', 'visits'])
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
                ->take(5)
                ->get();

            foreach ($bookings as $booking) {
                // Calculate duration
                $hours = floor($booking->duration_minutes / 60);
                $durationText = $hours > 0 ? "{$hours} Hours" : "{$booking->duration_minutes} Mins";
                $mediaService = $booking->service?->media()->where('collection', 'service/icon')->first();

                // Compute expected arrival time range
                $expectedArrivalTime = null;
                if ($booking->service && $booking->service->rules) {
                    $rules = $booking->service->rules;
                    $buffer = (int) ($rules->arrival_buffer_minutes ?? 0);
                    $grace = (int) ($rules->late_arrival_grace_minutes ?? 0);

                    $startTime = Carbon::parse($booking->start_time);
                    $fromTime = $startTime->copy()->subMinutes($buffer)->format('g:i A');
                    $toTime = $startTime->copy()->addMinutes($grace)->format('g:i A');
                    
                    $expectedArrivalTime = "{$fromTime} - {$toTime}";
                }

                // Compute first schedule date
                $startDate = Carbon::parse($booking->booking_date);
                $dayName = $startDate->isoFormat('dddd');
                $startDayAndDate = "{$dayName}, {$startDate->format('d M Y')}";

                $firstVisitDate = $booking->booking_date;
                $firstVisitFormatted = $startDayAndDate;
                if ($booking->relationLoaded('visits') && $booking->visits && $booking->visits->isNotEmpty()) {
                    $firstVisit = $booking->visits->sortBy('visit_date')->first();
                    $firstVisitDate = $firstVisit->visit_date;
                    
                    $fvDate = Carbon::parse($firstVisitDate);
                    $fvDayName = $fvDate->isoFormat('dddd');
                    $firstVisitFormatted = "{$fvDayName}, {$fvDate->format('d M Y')}";
                }

                $upcomingBookings[] = [
                    'id' => $booking->id,
                    'booking_ref' => $booking->booking_ref,
                    'service_name_ar' => $booking->service?->name_ar ?? '',
                    'service_name_en' => $booking->service?->name_en ?? '',
                    'image' => $mediaService ? (new MediaResource($mediaService))->getFileUrl() : null,
                    'price' => $booking->total_amount,
                    'currency' => 'EGP',
                    'status' => $booking->status,
                    'duration_text' => $durationText,
                    'date_day' => Carbon::parse($booking->booking_date)->format('d'),
                    'date_month' => Carbon::parse($booking->booking_date)->format('M'),
                    'time' => Carbon::parse($booking->start_time)->format('h:i A'),
                    'expected_arrival_time' => $expectedArrivalTime,
                    'first_schedule_date' => $firstVisitDate,
                    'first_schedule_date_formatted' => $firstVisitFormatted,
                    'visit_type' => $booking->visit_type ?? 'single_visit',
                ];
            }
        }

        // Return banners and upcoming_appointment (array of up to 5 items)
        return $this->successResponse([
            'banners' => AdResource::collection($banners),
            'upcoming_appointment' => $upcomingBookings,
            'upcoming_appointments' => $upcomingBookings,
        ], 'Home page data fetched successfully.');
    }
}
