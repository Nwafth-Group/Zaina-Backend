<?php

namespace App\Http\Resources\Api\V1\Booking;

use App\Models\CustomerAddress;
use App\Models\Report;
use App\Models\Review;
use App\Models\Service;
use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full booking payload for the admin details view: customer, service, schedule,
 * address, money breakdown, assigned workers and the status-change activity
 * feed. Relations are eager-loaded by the controller's `show`.
 */
class AdminBookingDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lang = app()->getLocale();
        $address = $this->whenLoaded('address') instanceof CustomerAddress ? $this->address : null;

        $childGroupsData = [];
        if ($this->child_age_groups && $this->service) {
            if (! $this->service->relationLoaded('childAgeGroups')) {
                $this->service->load('childAgeGroups');
            }
            $serviceGroups = $this->service->childAgeGroups;
            foreach ($this->child_age_groups as $groupId) {
                $group = $serviceGroups->firstWhere('id', $groupId);
                if ($group) {
                    $childGroupsData[] = [
                        'id' => $group->id,
                        'name_ar' => $group->name_ar,
                        'name_en' => $group->name_en,
                    ];
                } else {
                    $childGroupsData[] = [
                        'id' => $groupId,
                        'name_ar' => 'فئة عمرية غير معروفة',
                        'name_en' => 'Unknown Age Group',
                    ];
                }
            }
        }

        $pricePerPeriod = (float) ($this->service->base_price ?? 0);
        $visitsCount = (int) ($this->visit_number ?? 1);
        $workersCount = $this->relationLoaded('workers') && $this->workers ? $this->workers->count() : 1;
        $subtotalAmountVal = $pricePerPeriod * $visitsCount * $workersCount;

        $discountPercent = 0;
        if ($this->service) {
            $availableTypes = $this->service->available_visit_types ?? [];
            $visitTypeVal = $this->visit_type ?? 'single_visit';
            foreach ($availableTypes as $vt) {
                $vtType = $vt['type'] ?? '';
                if ($vtType === $visitTypeVal
                    || ($visitTypeVal === 'three_visits_per_week' && ($vtType === '3_days_per_week' || $vtType === '3_visits_per_week'))
                    || ($visitTypeVal === '3_days_per_week' && ($vtType === 'three_visits_per_week' || $vtType === '3_visits_per_week'))
                ) {
                    $discountPercent = (float) ($vt['discount'] ?? 0);
                    break;
                }
            }
        }
        $serviceDiscountAmount = $subtotalAmountVal * ($discountPercent / 100);
        $couponDiscountAmount = max(0.0, ((float) $this->discount_amount) - $serviceDiscountAmount);

        return [
            'id' => $this->id,
            'booking_ref' => $this->booking_ref,
            'status' => $this->status,
            'payment_status' => $this->payment_status,

            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'name' => trim(($this->customer->first_name ?? '').' '.($this->customer->last_name ?? '')),
                'phone' => $this->customer->phone_number,
                'email' => $this->customer->email,
            ] : null,

            'service_id' => $this->service_id,
            'service_name' => $this->service
                ? ($lang === 'ar' ? $this->service->name_ar : $this->service->name_en)
                : null,
            'service' => $this->service ? [
                'id' => $this->service->id,
                'service_code' => $this->service->service_code,
                'name' => $lang === 'ar' ? $this->service->name_ar : $this->service->name_en,
                'name_ar' => $this->service->name_ar,
                'name_en' => $this->service->name_en,
                'default_duration' => $this->service->default_duration,
                'is_team_based' => $this->service->isTeamBased(),
            ] : null,
            'is_team_based' => !empty($this->team_id) || ($this->service && $this->service->isTeamBased()),

            'booking_date' => $this->booking_date ? Carbon::parse($this->booking_date)->format('d M, Y') : null,
            'raw_booking_date' => $this->booking_date ? Carbon::parse($this->booking_date)->toDateString() : null,
            'raw_start_time' => $this->start_time,
            'raw_end_time' => $this->end_time,
            'time_slot' => ($this->start_time && $this->end_time)
                ? Carbon::parse($this->start_time)->format('h:i A').' - '.Carbon::parse($this->end_time)->format('h:i A')
                : null,
            'duration_minutes' => $this->duration_minutes,
            'visit_type' => $this->visit_type ?? 'single_visit',
            'visit_number' => (int) ($this->visit_number ?? 1),
            'visit_schedule' => $this->relationLoaded('visits') && $this->visits ? $this->visits->map(fn ($v) => [
                'id' => $v->id,
                'visit_date' => $v->visit_date,
                'visit_day' => Carbon::parse($v->visit_date)->format('l'),
                'start_time' => Carbon::parse($v->start_time)->format('H:i'),
                'end_time' => Carbon::parse($v->end_time)->format('H:i'),
                'actual_start_time' => $v->actual_start_time ? Carbon::parse($v->actual_start_time)->format('H:i') : null,
                'actual_end_time' => $v->actual_end_time ? Carbon::parse($v->actual_end_time)->format('H:i') : null,
                'duration' => (int) ceil($v->duration_minutes / 60),
                'visit_status' => $v->status,
            ])->values()->toArray() : [],
            'assigned_worker_ids' => $this->relationLoaded('workers') ? $this->workers->pluck('id')->toArray() : [],

            'workers' => $this->relationLoaded('workers')
                ? $this->workers->map(fn ($w) => ['id' => $w->id, 'name' => $w->name, 'nationality' => $w->nationality])->values()
                : [],

            'address' => $address ? [
                'id' => $address->id,
                'label' => $address->label,
                'formatted_address' => $address->formatted_address,
                'service_zone' => $address->serviceZone ? [
                    'id' => $address->serviceZone->id,
                    'name' => $address->serviceZone->name,
                    'city' => $address->serviceZone->city,
                    'governorate' => $address->serviceZone->governorate,
                ] : null,
                'line' => trim(implode(', ', array_filter([
                    $address->street,
                    $address->building_name,
                    $address->building_no ? "Bld {$address->building_no}" : null,
                    $address->floor ? "Floor {$address->floor}" : null,
                    $address->apartment_no ? "Apt {$address->apartment_no}" : null,
                    $address->serviceZone?->name,
                ]))),
                'phone' => $address->phone_number,
            ] : null,

            'price_summary' => [
                'subtotal' => (float) $this->price_subtotal,
                'tax' => (float) $this->tax_amount,
                'service_discount_amount' => (float) $serviceDiscountAmount,
                'coupon_discount_amount' => (float) $couponDiscountAmount,
                'has_coupon' => ! is_null($this->coupon_id),
                'coupon_id' => $this->coupon_id,
                'coupon_code' => $this->relationLoaded('coupon') && $this->coupon ? $this->coupon->code : null,
                'discount' => (float) $this->discount_amount,
                'total' => (float) $this->total_amount,
                'currency' => $this->currency_code ?? 'EGP',
                'payment_transaction_id' => $this->payment_transaction_id ?? '',
                'payment_method' => $this->payment_method,
            ],

            'team_id' => $this->team_id,
            'team' => $this->team ? [
                'id' => $this->team->id,
                'code' => $this->team->code,
                'name' => $lang === 'ar' ? $this->team->name_ar : $this->team->name_en,
                'name_ar' => $this->team->name_ar,
                'name_en' => $this->team->name_en,
                'status' => $this->team->status,
                'workers_count' => $this->team->workers ? $this->team->workers->count() : 0,
                'workers' => $this->team->workers ? $this->team->workers->map(fn($w) => [
                    'id' => $w->id,
                    'name' => $w->name,
                    'nationality' => $w->nationality,
                    'gender' => $w->gender,
                ])->values() : [],
            ] : null,

            'selected_options' => is_array($this->selected_options)
                ? $this->selected_options
                : json_decode($this->selected_options ?? '[]', true),

            'contract_id' => $this->contract_id ?? 1,
            'customer_notes' => $this->customer_notes,
            'number_of_children' => $this->number_of_children ? (int) $this->number_of_children : null,
            'child_age_groups' => $childGroupsData,
            'number_of_workers' => (int) ($this->number_of_workers ?? 1),
            'worker_gender' => $this->worker_gender,
            'is_reviewed' => Review::where('booking_id', $this->id)->exists(),
            'is_reported' => Report::where('booking_id', $this->id)->exists(),

            'activity' => $this->relationLoaded('histories')
                ? $this->histories->map(fn ($h) => [
                    'id' => $h->id,
                    'old_status' => $h->old_status,
                    'new_status' => $h->new_status,
                    'note' => $h->note,
                    'by' => $h->createdBy
                        ? trim(($h->createdBy->first_name ?? '').' '.($h->createdBy->last_name ?? ''))
                        : null,
                    'at' => $h->created_at?->toDateTimeString(),
                ])->values()
                : [],

            'payment_method' => $this->payment_method,
            'paid_points' => (int) $this->paid_points,
            'refund_amount' => (float) $this->refund_amount,
            'refund_points' => (int) $this->refund_points,
            'refund_destination' => $this->refund_destination,
            'refund_status' => $this->refund_status,
            'cancelled_by' => $this->cancelled_by,
            'cancelled_at' => $this->cancelled_at ? Carbon::parse($this->cancelled_at)->toIso8601String() : null,
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at?->toDateTimeString(),

            'reviews' => [
                'service' => ($serviceReview = Review::where('booking_id', $this->id)
                    ->where('reviewable_type', Service::class)
                    ->first()) ? [
                        'rating' => (int) $serviceReview->rating,
                        'comment' => $serviceReview->comment,
                        'percentage' => (float) $serviceReview->rating_percentage,
                    ] : null,
                'workers' => Review::where('booking_id', $this->id)
                    ->where('reviewable_type', Worker::class)
                    ->get()
                    ->map(function ($wReview) {
                        $workerName = Worker::find($wReview->reviewable_id)?->name ?? 'Unknown Worker';

                        return [
                            'worker_id' => $wReview->reviewable_id,
                            'worker_name' => $workerName,
                            'rating' => (int) $wReview->rating,
                            'comment' => $wReview->comment,
                            'percentage' => (float) $wReview->rating_percentage,
                        ];
                    })->toArray(),
            ],
        ];
    }
}
