<?php

namespace App\Service\Customer;

use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\BookingHistory;
use App\Models\BookingVisit;
use App\Models\Coupon;
use App\Models\CustomerAddress;
use App\Models\Service;
use App\Models\ServicePrice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CustomerBookingService
{
    /**
     * Step 1: Validate parameters, allocate available workers, check pricing.
     */
    public function allocateWorkersAndCalculatePrice($user, array $data): array
    {
        if (!$user) {
            throw new \Exception('Unauthenticated', 401);
        }

        // 1. Fetch address and service models
        [$address, $service] = $this->validateAddressAndService($user, $data);

        // 2. Perform wallet checks (user cannot have negative balance penalties)
        if ($user->wallet_balance < 0) {
            throw new \Exception(__('messages.unpaid_penalties', ['amount' => abs($user->wallet_balance)]), 402);
        }

        $dates = $data['dates'] ?? [];
        $startTime = $data['start_time'];

        // 3. Compute duration and start/end datetimes
        $times = $this->calculateBookingTimes($service, $dates[0], $startTime);

        // Check if customer already has an active booking at the same date and overlapping shift
        if ($this->hasOverlappingCustomerBooking($user->id, $dates, $times['start_str'], $times['end_str'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'start_time' => __('validation.create_booking.customer_overlap')
            ]);
        }

        // 4. Validate that all dates fit within active service shifts/schedules
        foreach ($dates as $date) {
            $this->validateShiftBoundaries($service, $date, $times['start_str'], $times['end_str']);
        }

        // 5. Query busy workers across ALL dates (combines database overlaps & temporary cache locks)
        $busyWorkerIds = $this->getBusyWorkerIdsForDates($service, $dates, $times['start_str'], $times['end_str']);

        // 6. Get active, non-busy workers
        $availableWorkers = $service->workers()
            ->where('status', 'active')
            ->whereNotIn('workers.id', $busyWorkerIds)
            ->get();

        // 7. Allocate preferred workers first, then randomly fill slots up to workers_count
        $allocatedWorkers = $this->allocateWorkers($availableWorkers, (int) $data['workers_count'], $data['preferred_worker_ids'] ?? []);

        // 8. Calculate subtotal, discount, VAT, and total
        $pricing = $this->calculatePricing(
            $service, 
            $address, 
            $allocatedWorkers->count(), 
            count($dates), 
            $data['visit_type'] ?? 'single_visit', 
            $data['coupon_code'] ?? null
        );

        return [
            'worker_ids' => $allocatedWorkers->pluck('id')->toArray(),
            'pricing'    => $pricing['pricing'],
        ];
    }

    /**
     * Step 2: Pay and persist the booking to the database with active worker assignments and child visits.
     */
    public function createBookingV2($user, array $data)
    {
        if (!$user) {
            throw new \Exception('Unauthenticated', 401);
        }

        // 1. Fetch address and service models
        [$address, $service] = $this->validateAddressAndService($user, $data);

        // 2. Enforce wallet checks
        if ($user->wallet_balance < 0) {
            throw new \Exception(__('messages.unpaid_penalties', ['amount' => abs($user->wallet_balance)]), 402);
        }

        $dates = $data['dates'] ?? [];
        $startTime = $data['start_time'];

        // 3. Compute times
        $times = $this->calculateBookingTimes($service, $dates[0], $startTime);

        // Check if customer already has an active booking at the same date and overlapping shift
        if ($this->hasOverlappingCustomerBooking($user->id, $dates, $times['start_str'], $times['end_str'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'start_time' => __('validation.create_booking.customer_overlap')
            ]);
        }

        // 4. Validate shift boundaries for all dates
        foreach ($dates as $date) {
            $this->validateShiftBoundaries($service, $date, $times['start_str'], $times['end_str']);
        }

        $workerIds = $data['worker_ids'];

        // 5. Ensure allocated workers are active and not double-booked (overlap check) across all dates
        foreach ($workerIds as $workerId) {
            $workerExists = $service->workers()
                ->where('workers.id', $workerId)
                ->where('workers.status', 'active')
                ->exists();

            if (!$workerExists) {
                throw new \Exception(__('messages.invalid_worker_for_service'), 422);
            }

            $hasConflict = BookingAssignment::where('worker_id', $workerId)
                ->where('status', 'active')
                ->whereHas('booking', function ($q) use ($dates, $times) {
                    $q->whereIn('booking_date', $dates)
                        ->whereIn('status', ['Upcoming', 'inService'])
                        ->where(function ($timeQ) use ($times) {
                            $timeQ->where('start_time', '<', $times['end_str'])
                                  ->where('end_time', '>', $times['start_str']);
                        });
                })
                ->exists();

            if ($hasConflict) {
                throw new \Exception(__('messages.worker_already_booked'), 422);
            }
        }

        // 6. Calculate prices
        $pricing = $this->calculatePricing(
            $service, 
            $address, 
            count($workerIds), 
            count($dates), 
            $data['visit_type'] ?? 'single_visit', 
            $data['coupon_code'] ?? null
        );
        $totalAmount = $pricing['pricing']['total'];

        // 7. Verify user payment balance (points or wallet)
        $paymentMethod = $data['payment_method'];
        $this->verifyPaymentBalance($user, $paymentMethod, $totalAmount);

        // 8. Run actual database transaction for insertion
        DB::beginTransaction();
        try {
            // Lock database records to guard against parallel transactions
            BookingAssignment::whereIn('worker_id', $workerIds)
                ->where('status', 'active')
                ->whereHas('booking', function ($q) use ($dates, $times) {
                    $q->whereIn('booking_date', $dates)
                        ->whereIn('status', ['Upcoming', 'inService'])
                        ->where(function ($timeQ) use ($times) {
                            $timeQ->where('start_time', '<', $times['end_str'])
                                  ->where('end_time', '>', $times['start_str']);
                        });
                })
                ->lockForUpdate()
                ->exists();

            // Create Booking (using first date as parent reference)
            $booking = Booking::create([
                'booking_ref'            => 'HC-' . rand(100000, 999999),
                'customer_id'            => $user->id,
                'service_id'             => $data['service_id'],
                'address_id'             => $data['address_id'],
                'worker_id'              => $workerIds[0],
                'coupon_id'              => $pricing['coupon'] ? $pricing['coupon']->id : null,
                'booking_date'           => $dates[0],
                'start_time'             => $times['start_str'],
                'end_time'               => $times['end_str'],
                'duration_minutes'       => $times['duration_minutes'],
                'price_subtotal'         => $pricing['pricing']['subtotal'],
                'tax_amount'             => $pricing['pricing']['tax'],
                'discount_amount'        => $pricing['pricing']['discount'],
                'total_amount'           => $totalAmount,
                'status'                 => 'Upcoming',
                'payment_status'         => 'paid',
                'payment_method'         => $paymentMethod,
                'visit_type'             => $data['visit_type'] ?? 'single_visit',
                'visit_number'           => count($dates),
                'payment_transaction_id' => 'TXN-' . rand(10000000, 99999999),
                'currency_code'          => 'EGP',
                'contract_id'            => 1,
            ]);

            // Create Booking Visits (One row per scheduled date)
            foreach ($dates as $date) {
                BookingVisit::create([
                    'booking_id' => $booking->id,
                    'visit_date' => $date,
                    'start_time' => $times['start_str'],
                    'end_time'   => $times['end_str'],
                    'status'     => 'Upcoming',
                ]);
            }

            // Deduct funds
            if ($paymentMethod === 'points') {
                $user->pointTransactions()->create([
                    'type' => 'redeem',
                    'amount' => (int) ceil($totalAmount),
                    'title_ar' => 'دفع مقابل حجز برقم مرجعي: ' . $booking->booking_ref,
                    'title_en' => 'Payment for Booking Ref: ' . $booking->booking_ref,
                ]);
            } elseif ($paymentMethod === 'wallet') {
                $user->withdraw($totalAmount, 'Payment - Booking', $booking->booking_ref);
            }

            // Create Worker Assignments
            foreach ($workerIds as $workerId) {
                BookingAssignment::create([
                    'booking_id'  => $booking->id,
                    'worker_id'   => $workerId,
                    'status'      => 'active',
                    'assigned_at' => now(),
                ]);
            }

            if ($pricing['coupon']) {
                $pricing['coupon']->increment('used_count');
            }

            BookingHistory::create([
                'booking_id' => $booking->id,
                'new_status' => 'Upcoming',
                'note'       => "تم إنشاء الحجز والدفع بنجاح عبر ({$paymentMethod}). إجمالي المبلغ: {$totalAmount} جنيه.",
                'created_by' => $user->id,
            ]);

            DB::commit();

            // 9. Successfully booked. Release the temporary cache holds for these workers.
            $this->releaseWorkerReservationsForDates($workerIds, $dates, $times['start_str'], $times['end_str']);

            return $booking;

        } catch (\Exception $transactionEx) {
            DB::rollBack();
            throw $transactionEx;
        }
    }

    private function validateAddressAndService($user, array $data): array
    {
        $addressId = $data['address_id'] ?? null;

        if (!$addressId) {
            $address = CustomerAddress::with('area')
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->first();

            if (!$address) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'address_id' => 'Please add or select a service address first.'
                ]);
            }
        } else {
            $address = CustomerAddress::with('area')
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->findOrFail($addressId);
        }

        $service = Service::with('duration')->findOrFail($data['service_id']);

        $hasCoverage = $service->coverages()
            ->where('city_id', $address->area->city_id)
            ->where(function ($q) use ($address) {
                $q->where('area_id', $address->area_id)->orWhereNull('area_id');
            })
            ->where('status', 'active')
            ->exists();

        if (!$hasCoverage) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'address_id' => __('messages.service_not_available_in_location') ?? 'هذه الخدمة غير متوفرة في موقعك الحالي.'
            ]);
        }

        return [$address, $service];
    }

    private function calculateBookingTimes(Service $service, string $date, string $startTime): array
    {
        $periodHours = $service->duration->service_period_hours ?? 2;
        $durationMinutes = $periodHours * 60;

        $start = Carbon::parse($startTime);
        $end = $start->copy()->addMinutes($durationMinutes);

        return [
            'start'            => $start,
            'end'              => $end,
            'start_str'        => $start->format('H:i:s'),
            'end_str'          => $end->format('H:i:s'),
            'duration_minutes' => $durationMinutes,
        ];
    }

    private function validateShiftBoundaries(Service $service, string $date, string $startStr, string $endStr): void
    {
        $dayName = strtolower(Carbon::parse($date)->format('l'));
        $hasValidShift = $service->schedules()
            ->where('day', $dayName)
            ->where('is_active', true)
            ->where('start_time', '<=', $startStr)
            ->where('end_time', '>=', $endStr)
            ->exists();

        if (!$hasValidShift) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'start_time' => __('validation.create_booking.shift_invalid')
            ]);
        }
    }

    /**
     * Query busy workers across all dates combining active DB assignments & temporary lock holds.
     */
    private function getBusyWorkerIdsForDates(Service $service, array $dates, string $startStr, string $endStr): array
    {
        $allBusyIds = [];
        foreach ($dates as $date) {
            $busyIds = $this->getBusyWorkerIds($service, $date, $startStr, $endStr);
            $allBusyIds = array_merge($allBusyIds, $busyIds);
        }
        return array_values(array_unique($allBusyIds));
    }

    /**
     * Query busy workers for a specific date.
     */
    private function getBusyWorkerIds(Service $service, string $bookingDate, string $startStr, string $endStr): array
    {
        $dbBusyIds = BookingAssignment::where('status', 'active')
            ->whereHas('booking', function ($q) use ($bookingDate, $startStr, $endStr) {
                $q->where('booking_date', $bookingDate)
                    ->whereIn('status', ['Upcoming', 'inService'])
                    ->where(function ($timeQ) use ($startStr, $endStr) {
                        $timeQ->where('start_time', '<', $endStr)
                              ->where('end_time', '>', $startStr);
                    });
            })
            ->pluck('worker_id')
            ->unique()
            ->toArray();

        $cacheBusyIds = [];
        $allWorkerIds = $service->workers()->where('status', 'active')->pluck('workers.id')->toArray();
        $currentUserId = auth()->id();

        foreach ($allWorkerIds as $workerId) {
            $holds = Cache::get("temp_hold_worker_{$workerId}", []);
            $hasOverlapHold = false;

            foreach ($holds as $hold) {
                if (isset($hold['customer_id']) && $hold['customer_id'] == $currentUserId) {
                    continue;
                }
                if (now()->timestamp < $hold['expires_at']) {
                    if ($hold['date'] === $bookingDate && $startStr < $hold['end_time'] && $endStr > $hold['start_time']) {
                        $hasOverlapHold = true;
                        break;
                    }
                }
            }
            if ($hasOverlapHold) {
                $cacheBusyIds[] = $workerId;
            }
        }

        return array_unique(array_merge($dbBusyIds, $cacheBusyIds));
    }

    /**
     * Allocate workers prioritising user's preferred list.
     */
    private function allocateWorkers($availableWorkers, int $workersCount, array $preferredInput): \Illuminate\Support\Collection
    {
        $allocated = collect();

        // Match preferred workers first
        if (!empty($preferredInput)) {
            $preferredAvailable = $availableWorkers->whereIn('id', $preferredInput)->values();
            $allocated = $allocated->merge($preferredAvailable);
        }

        $needed = $workersCount - $allocated->count();
        if ($needed > 0) {
            $remaining = $availableWorkers->whereNotIn('id', $allocated->pluck('id'))->values();
            if ($remaining->count() < $needed) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'workers_count' => 'Not enough workers available for this shift and date.'
                ]);
            }
            $randomPicked = $remaining->random($needed)->values();
            $allocated = $allocated->merge($randomPicked);
        }

        return $allocated;
    }

    /**
     * Calculate price calculations: subtotal, service discount, coupon discount, tax (VAT=0), and total.
     */
    private function calculatePricing(Service $service, CustomerAddress $address, int $workersCount, int $visitsCount, string $visitType, ?string $couponCode = null): array
    {
        $pricePerPeriod = (float) ($service->base_price ?? 0);
        $subtotal = $pricePerPeriod * $visitsCount * $workersCount;

        // Resolve Service Discount (from service->available_visit_types)
        $discountPercent = 0;
        $availableTypes = $service->available_visit_types ?? [];
        foreach ($availableTypes as $vt) {
            $vtType = $vt['type'] ?? '';
            if ($vtType === $visitType 
                || ($visitType === 'three_visits_per_week' && ($vtType === '3_days_per_week' || $vtType === '3_visits_per_week'))
                || ($visitType === '3_days_per_week' && ($vtType === 'three_visits_per_week' || $vtType === '3_visits_per_week'))
            ) {
                $discountPercent = (float) ($vt['discount'] ?? 0);
                break;
            }
        }

        $serviceDiscountAmount = $subtotal * ($discountPercent / 100);
        $amountAfterServiceDiscount = $subtotal - $serviceDiscountAmount;

        $couponDiscountAmount = 0;
        $coupon = null;

        if (!empty($couponCode)) {
            $now = now();
            $coupon = Coupon::where('code', $couponCode)
                ->where('status', 'active')
                ->where(function ($q) use ($now) {
                    $q->whereNull('start_date')->orWhere('start_date', '<=', $now);
                })
                ->where(function ($q) use ($now) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', $now);
                })
                ->first();

            if (!$coupon) {
                throw new \Exception(__('messages.invalid_or_expired_coupon'), 422);
            }

            if ($coupon->type === 'fixed') {
                $couponDiscountAmount = (float) $coupon->value;
            } elseif ($coupon->type === 'percentage') {
                $couponDiscountAmount = $amountAfterServiceDiscount * ($coupon->value / 100);
                if ($coupon->max_discount && $couponDiscountAmount > $coupon->max_discount) {
                    $couponDiscountAmount = (float) $coupon->max_discount;
                }
            }
            if ($couponDiscountAmount > $amountAfterServiceDiscount) {
                $couponDiscountAmount = $amountAfterServiceDiscount;
            }
        }

        $tax = 0;
        $totalDiscount = $serviceDiscountAmount + $couponDiscountAmount;
        $total = ($subtotal - $totalDiscount) + $tax;

        return [
            'coupon'     => $coupon,
            'pricing'    => [
                'price_per_period'        => $pricePerPeriod,
                'workers_count'           => $workersCount,
                'visits_count'            => $visitsCount,
                'subtotal'                => $subtotal,
                'service_discount_amount' => $serviceDiscountAmount,
                'coupon_discount_amount'  => $couponDiscountAmount,
                'discount'                => $totalDiscount,
                'tax'                     => $tax,
                'total'                   => $total,
                'currency'                => 'EGP'
            ]
        ];
    }

    private function verifyPaymentBalance($user, string $paymentMethod, float $totalAmount): void
    {
        if ($paymentMethod === 'points') {
            $totalEarnedPoints = $user->pointTransactions()->where('type', 'earn')->sum('amount');
            $totalRedeemedPoints = $user->pointTransactions()->where('type', 'redeem')->sum('amount');
            $userPointsBalance = $totalEarnedPoints - $totalRedeemedPoints;

            $pointsRequired = (int) ceil($totalAmount);
            if ($userPointsBalance < $pointsRequired) {
                throw new \Exception("عذراً، رصيد نقاطك الحالي ({$userPointsBalance} نقطة) غير كافٍ. يتطلب الحجز {$pointsRequired} نقطة.", 422);
            }
        } elseif ($paymentMethod === 'wallet') {
            if ($user->wallet_balance < $totalAmount) {
                if (app()->environment('local', 'testing')) {
                    $deficit = $totalAmount - $user->wallet_balance;
                    $user->deposit($deficit, 'Auto Topup - Development Mock Mode');
                } else {
                    throw new \Exception(__('messages.insufficient_balance'), 402);
                }
            }
        }
    }

    /**
     * Reserve worker slots in Laravel cache for 10 minutes (for multiple dates).
     */
    private function reserveWorkersTemporarilyForDates(array $workerIds, array $dates, string $startStr, string $endStr): void
    {
        $customerId = auth()->id();
        foreach ($workerIds as $workerId) {
            $holds = Cache::get("temp_hold_worker_{$workerId}", []);
            $holds = collect($holds)->filter(fn($h) => now()->timestamp < $h['expires_at'])->toArray();

            foreach ($dates as $date) {
                $holds[] = [
                    'date'        => $date,
                    'start_time'  => $startStr,
                    'end_time'    => $endStr,
                    'expires_at'  => now()->addMinutes(5)->timestamp,
                    'customer_id' => $customerId,
                ];
            }

            Cache::put("temp_hold_worker_{$workerId}", $holds, now()->addMinutes(10));
        }
    }

    /**
     * Release active temporary holds after booking completion.
     */
    private function releaseWorkerReservationsForDates(array $workerIds, array $dates, string $startStr, string $endStr): void
    {
        foreach ($workerIds as $workerId) {
            $holds = Cache::get("temp_hold_worker_{$workerId}", []);
            
            // Exclude current booking holds and expired holds
            $holds = collect($holds)
                ->filter(fn($h) => now()->timestamp < $h['expires_at'])
                ->filter(fn($h) => !(in_array($h['date'], $dates) && $h['start_time'] === $startStr && $h['end_time'] === $endStr))
                ->toArray();

            if (empty($holds)) {
                Cache::forget("temp_hold_worker_{$workerId}");
            } else {
                Cache::put("temp_hold_worker_{$workerId}", $holds, now()->addMinutes(15));
            }
        }
    }

    /**
     * Check if the customer already has an active booking at the same date and overlapping shift.
     */
    private function hasOverlappingCustomerBooking(int $userId, array $dates, string $startStr, string $endStr): bool
    {
        return \App\Models\Booking::where('customer_id', $userId)
            ->whereIn('status', ['Upcoming', 'inService'])
            ->where(function ($q) use ($dates, $startStr, $endStr) {
                // Check if any child booking visit overlaps
                $q->whereHas('visits', function ($visitQ) use ($dates, $startStr, $endStr) {
                    $visitQ->whereIn('visit_date', $dates)
                        ->where('start_time', '<', $endStr)
                        ->where('end_time', '>', $startStr);
                })
                // Fallback compatibility check if visits are not populated yet
                ->orWhere(function ($parentQ) use ($dates, $startStr, $endStr) {
                    $parentQ->whereIn('booking_date', $dates)
                        ->where('start_time', '<', $endStr)
                        ->where('end_time', '>', $startStr);
                });
            })
            ->exists();
    }
}
