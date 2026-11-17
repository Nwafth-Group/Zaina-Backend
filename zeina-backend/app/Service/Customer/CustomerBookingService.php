<?php

namespace App\Service\Customer;

use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\BookingHistory;
use App\Models\BookingVisit;
use App\Models\CompanyInfo;
use App\Models\Coupon;
use App\Models\CustomerAddress;
use App\Models\Service;
use App\Models\ServiceOption;
use App\Models\Team;
use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerBookingService
{
    /**
     * Step 1: Validate parameters, allocate available workers, check pricing.
     */
    public function allocateWorkersAndCalculatePrice($user, array $data): array
    {
        if (! $user) {
            throw new \Exception('Unauthenticated', 401);
        }

        // 1. Fetch address and service models
        [$address, $service] = $this->validateAddressAndService($user, $data);

        $workersCount = $data['workers_count'] ?? null;
        if ($service->isTeamBased()) {
            $data['workers_count'] = $service->min_workers ?? 3;
            $data['duration'] = $service->default_duration ?? 2;
            $data['worker_gender'] = 'Male';
        } elseif ($service->service_code === 'DEEP_CLEANING') {
            $data['workers_count'] = $service->min_workers ?? 6;
            $data['duration'] = $service->default_duration ?? 6;
            $genderOpts = $service->worker_gender_options ?? [];
            $data['worker_gender'] = in_array('Male', $genderOpts) ? 'Male' : (count($genderOpts) > 0 ? $genderOpts[0] : 'Male');
        } elseif ($service->service_code === 'BABYSITTER_HOURLY' || $service->service_code === 'DOMESTIC_HELPER_HOURLY' || $service->service_code === 'PEDICURE_MANICURE') {
            $data['worker_gender'] = 'Female';
        } else {
            // Worker-based specialized services (Services 6 to 11 when set to individual workers):
            $genderOpts = $service->worker_gender_options ?? [];
            if (empty($data['workers_count'])) {
                $data['workers_count'] = $service->min_workers ?? 1;
            }
            if (empty($data['duration'])) {
                $data['duration'] = $service->default_duration ?? 2;
            }
            if (empty($data['worker_gender'])) {
                $data['worker_gender'] = in_array('Male', $genderOpts) ? 'Male' : (count($genderOpts) > 0 ? $genderOpts[0] : 'Male');
            }
        }

        if ($service->service_code === 'BABYSITTER_HOURLY') {
            $calculatedMinWorkers = $this->calculateMinimumBabysitters($service, $data['child_age_groups'] ?? []);
            $maxWorkers = $service->max_workers ?? 5;
            if ($calculatedMinWorkers > $maxWorkers) {
                throw new \Exception(__('validation.create_booking.babysitters_count_max', ['max' => $maxWorkers]), 422);
            }
            if ($workersCount === null) {
                $workersCount = $calculatedMinWorkers;
            } else {
                $workersCount = (int) $workersCount;
                if ($workersCount < $calculatedMinWorkers) {
                    $breakdown = $this->getBabysitterBreakdown($service, $data['child_age_groups'] ?? []);
                    $msg = __('validation.create_booking.babysitters_count_min', ['min' => $calculatedMinWorkers]).' ('.$breakdown.')';
                    throw new \Exception($msg, 422);
                }
                if ($workersCount > $maxWorkers) {
                    throw new \Exception(__('validation.create_booking.babysitters_count_max', ['max' => $maxWorkers]), 422);
                }
            }
            $data['workers_count'] = $workersCount;
        }

        $dates = collect($data['dates'] ?? [])->sort()->values()->toArray();
        $startTime = $data['start_time'] ?? '08:00';

        if ($service->service_code === 'PEDICURE_MANICURE') {
            $numberOfPersons = (int) ($data['number_of_persons'] ?? 1);
            $calculatedMinWorkers = $this->calculateMinimumPedicureWorkers($service, $numberOfPersons);
            $maxWorkers = $service->max_workers ?? 10;
            $maxAllowedWorkers = min($numberOfPersons, $maxWorkers);

            if ($workersCount === null) {
                $tested = $this->determinePedicureActualMinWorkers($service, $dates, $startTime, $numberOfPersons, $calculatedMinWorkers, $maxAllowedWorkers);
                $workersCount = $tested['workers_count'];
                $duration = $tested['duration'];
            } else {
                $workersCount = (int) $workersCount;
                if ($workersCount < $calculatedMinWorkers) {
                    $isArabic = app()->getLocale() === 'ar';
                    $msg = $isArabic
                        ? "الحد الأدنى للعمالة المطلوبة لعدد {$numberOfPersons} أشخاص هو {$calculatedMinWorkers} عاملة."
                        : "Minimum required workers for {$numberOfPersons} person(s) is {$calculatedMinWorkers}.";
                    throw new \Exception($msg, 422);
                }
                if ($workersCount > $maxAllowedWorkers) {
                    $isArabic = app()->getLocale() === 'ar';
                    $msg = $isArabic
                        ? "الحد الأقصى للعمالة المسموح به لهذا الحجز هو {$maxAllowedWorkers} عاملة."
                        : "Maximum allowed workers for this booking is {$maxAllowedWorkers}.";
                    throw new \Exception($msg, 422);
                }
                $duration = $this->calculatePedicureDuration($service, $numberOfPersons, $workersCount);
            }
            $data['workers_count'] = $workersCount;
            $data['duration'] = $duration;
            $data['worker_gender'] = 'Female';
        }

        // 2. Perform wallet checks (user cannot have negative balance penalties)
        if ($user->wallet_balance < 0) {
            throw new \Exception(__('messages.unpaid_penalties', ['amount' => abs($user->wallet_balance)]), 402);
        }

        // Check if customer already has an active booking for this service (not completed or closed)
        if ($this->hasActiveCustomerBooking($user->id, $service->id)) {
            throw ValidationException::withMessages([
                'service_id' => __('validation.create_booking.active_service_booking_exists'),
            ]);
        }

        // 3. Compute duration and start/end datetimes
        $times = $this->calculateBookingTimes($service, $dates[0], $startTime, $data['duration'] ?? null);

        // Check if customer already has an active booking at the same date and overlapping shift
        if ($this->hasOverlappingCustomerBooking($user->id, $dates, $times['start_str'], $times['end_str'], $service->id)) {
            throw ValidationException::withMessages([
                'start_time' => __('validation.create_booking.customer_overlap'),
            ]);
        }

        // 4. Validate that all dates fit within active service shifts/schedules
        foreach ($dates as $date) {
            $this->validateShiftBoundaries($service, $date, $times['start_str'], $times['end_str']);
        }

        // 5. Query busy workers across ALL dates (combines database overlaps & temporary cache locks)
        $busyWorkerIds = $this->getBusyWorkerIdsForDates($service, $dates, $times['start_str'], $times['end_str']);

        // 6. Get active, non-busy workers (Enforce gender matching service rules & booking request)
        $gender = $data['worker_gender'] ?? null;
        if (empty($gender)) {
            $allowed = $service->getAllowedGenders();
            if (count($allowed) === 1) {
                $gender = $allowed[0];
            }
        }

        $availableWorkersQuery = $service->workers()
            ->where('workers.status', 'active')
            ->whereNotIn('workers.id', $busyWorkerIds);

        if ($gender) {
            $availableWorkersQuery->where('workers.gender', strtolower($gender));
        }

        $availableWorkers = $availableWorkersQuery->get();

        // 7. Allocate team for team-based services, or individual workers for standard services
        $isTeamService = $service->isTeamBased();
        if ($isTeamService) {
            $conflictService = app(\App\Service\Booking\BookingScheduleConflictService::class);
            $selectedTeam = null;
            $teams = $service->teams()->where('status', 'active')->with('workers')->get();
            foreach ($teams as $team) {
                $teamAvailableAllDates = true;
                foreach ($dates as $date) {
                    if (!$conflictService->isTeamAvailable($team, $date, $times['start_str'], $times['end_str'])) {
                        $teamAvailableAllDates = false;
                        break;
                    }
                }
                if ($teamAvailableAllDates) {
                    $selectedTeam = $team;
                    break;
                }
            }
            if (!$selectedTeam) {
                throw ValidationException::withMessages([
                    'team' => __('messages.no_teams_available') ?? 'لا يوجد فريق عمل متاح لهذا الموعد.',
                ]);
            }
            if ($selectedTeam->workers->isEmpty()) {
                throw ValidationException::withMessages([
                    'team' => 'فريق العمل المتاح لا يحتوي على عمالة مسجلة.',
                ]);
            }
            $allocatedWorkers = $selectedTeam->workers;
        } else {
            $allocatedWorkers = $this->allocateWorkers($availableWorkers, (int) $data['workers_count'], $data['preferred_worker_ids'] ?? []);
        }

        // 8. Calculate subtotal, discount, VAT, and total
        $options = $data['options'] ?? $data['selected_options'] ?? [];
        $pricing = $this->calculatePricing(
            $service,
            $address,
            $isTeamService ? ($allocatedWorkers->count() ?: ($service->min_workers ?? 3)) : $allocatedWorkers->count(),
            count($dates),
            $data['visit_type'] ?? 'single_visit',
            $data['coupon_code'] ?? null,
            $user,
            $times['start_str'],
            $times['end_str'],
            (int) ($data['number_of_persons'] ?? 1),
            $options
        );

        $res = [
            'worker_ids' => $allocatedWorkers->pluck('id')->toArray(),
            'pricing' => $pricing['pricing'],
        ];
        if ($isTeamService) {
            $res['team_id'] = ($selectedTeam && $selectedTeam->id) ? $selectedTeam->id : null;
        }

        if ($service->service_code === 'PEDICURE_MANICURE') {
            $res['duration'] = (int) $data['duration'];
            $res['number_of_persons'] = (int) ($data['number_of_persons'] ?? 1);
            $res['persons_distribution'] = $this->calculatePedicurePersonsDistribution((int) ($data['number_of_persons'] ?? 1), $allocatedWorkers->count());
        }

        return $res;
    }

    /**
     * Step 2: Pay and persist the booking to the database with active worker assignments and child visits.
     */
    public function createBookingV2($user, array $data)
    {
        if (! $user) {
            throw new \Exception('Unauthenticated', 401);
        }

        // 1. Fetch address and service models
        [$address, $service] = $this->validateAddressAndService($user, $data);

        if ($service->isTeamBased()) {
            $data['duration'] = $service->default_duration ?? 2;
            $data['visit_type'] = 'single_visit';
            $data['worker_gender'] = 'Male';
        } elseif ($service->service_code === 'DEEP_CLEANING') {
            $data['duration'] = $service->default_duration ?? 6;
            if (empty($data['worker_gender'])) {
                $genderOpts = $service->worker_gender_options ?? [];
                $data['worker_gender'] = in_array('Male', $genderOpts) ? 'Male' : (count($genderOpts) > 0 ? $genderOpts[0] : 'Male');
            }
        } elseif ($service->service_code === 'BABYSITTER_HOURLY') {
            $calculatedMinWorkers = $this->calculateMinimumBabysitters($service, $data['child_age_groups'] ?? []);
            $maxWorkers = $service->max_workers ?? 5;
            if ($calculatedMinWorkers === 1) {
                $maxWorkers = 1;
            }
            $workerIdsCount = count($data['worker_ids'] ?? []);
            if ($calculatedMinWorkers > $maxWorkers) {
                throw new \Exception(__('validation.create_booking.babysitters_count_max', ['max' => $maxWorkers]), 422);
            }
            if ($workerIdsCount < $calculatedMinWorkers) {
                $breakdown = $this->getBabysitterBreakdown($service, $data['child_age_groups'] ?? []);
                $msg = __('validation.create_booking.babysitters_count_min', ['min' => $calculatedMinWorkers]).' ('.$breakdown.')';
                throw new \Exception($msg, 422);
            }
            if ($workerIdsCount > $maxWorkers) {
                throw new \Exception(__('validation.create_booking.babysitters_count_max', ['max' => $maxWorkers]), 422);
            }
            if (empty($data['worker_gender'])) {
                $data['worker_gender'] = 'Female';
            }
        } elseif ($service->service_code === 'PEDICURE_MANICURE') {
            $numberOfPersons = (int) ($data['number_of_persons'] ?? 1);
            $calculatedMinWorkers = $this->calculateMinimumPedicureWorkers($service, $numberOfPersons);
            $workerIdsCount = count($data['worker_ids'] ?? []);
            if ($workerIdsCount < $calculatedMinWorkers) {
                $isArabic = app()->getLocale() === 'ar';
                $msg = $isArabic
                    ? "الحد الأدنى للعمالة المطلوبة لعدد {$numberOfPersons} أشخاص هو {$calculatedMinWorkers} عاملة."
                    : "Minimum required workers for {$numberOfPersons} person(s) is {$calculatedMinWorkers}.";
                throw new \Exception($msg, 422);
            }
            $maxAllowedWorkers = min($numberOfPersons, $service->max_workers ?? 10);
            if ($workerIdsCount > $maxAllowedWorkers) {
                $isArabic = app()->getLocale() === 'ar';
                $msg = $isArabic
                    ? "الحد الأقصى للعمالة المسموح به لهذا الحجز هو {$maxAllowedWorkers} عاملة."
                    : "Maximum allowed workers for this booking is {$maxAllowedWorkers}.";
                throw new \Exception($msg, 422);
            }
            $data['duration'] = $this->calculatePedicureDuration($service, $numberOfPersons, $workerIdsCount);
            if (empty($data['worker_gender'])) {
                $data['worker_gender'] = 'Female';
            }
        } else {
            // Worker-based specialized services (Services 6 to 11 when set to individual workers):
            if (empty($data['duration'])) {
                $data['duration'] = $service->default_duration ?? 2;
            }
            if (empty($data['worker_gender'])) {
                $genderOpts = $service->worker_gender_options ?? [];
                $data['worker_gender'] = in_array('Male', $genderOpts) ? 'Male' : (count($genderOpts) > 0 ? $genderOpts[0] : 'Male');
            }
        }

        // 2. Enforce wallet checks
        if ($user->wallet_balance < 0) {
            throw new \Exception(__('messages.unpaid_penalties', ['amount' => abs($user->wallet_balance)]), 402);
        }

        // Check if customer already has an active booking for this service (not completed or closed)
        if ($this->hasActiveCustomerBooking($user->id, $service->id)) {
            throw ValidationException::withMessages([
                'service_id' => __('validation.create_booking.active_service_booking_exists'),
            ]);
        }

        $dates = collect($data['dates'] ?? [])->sort()->values()->toArray();
        $startTime = $data['start_time'] ?? '08:00';

        // 3. Compute times
        $times = $this->calculateBookingTimes($service, $dates[0], $startTime, $data['duration'] ?? null);

        // Check if customer already has an active booking at the same date and overlapping shift
        if ($this->hasOverlappingCustomerBooking($user->id, $dates, $times['start_str'], $times['end_str'], $service->id)) {
            throw ValidationException::withMessages([
                'start_time' => __('validation.create_booking.customer_overlap'),
            ]);
        }

        // 4. Validate shift boundaries for all dates
        foreach ($dates as $date) {
            $this->validateShiftBoundaries($service, $date, $times['start_str'], $times['end_str']);
        }

        $isTeamService = $service->isTeamBased();
        $workerIds = $data['worker_ids'] ?? [];

        if (!$isTeamService) {
            if (empty($workerIds)) {
                throw new \Exception(__('validation.create_booking.worker_ids_required'), 422);
            }
            $requiredWorkerCount = (int) ($data['workers_count'] ?? $service->min_workers ?? 1);
            if (count($workerIds) < $requiredWorkerCount) {
                $isAr = app()->getLocale() === 'ar';
                $msg = $isAr
                    ? "يجب اختيار {$requiredWorkerCount} عمال على الأقل لهذا الحجز."
                    : "You must select at least {$requiredWorkerCount} workers for this booking.";
                throw new \Exception($msg, 422);
            }
        }

        // 5. Ensure allocated workers are active and not double-booked (overlap check) across all dates (if workers are provided)
        if (!empty($workerIds)) {
            foreach ($workerIds as $workerId) {
                $workerQuery = $service->workers()
                    ->where('workers.id', $workerId)
                    ->where('workers.status', 'active');

                $gender = $data['worker_gender'] ?? null;
                if (empty($gender)) {
                    $allowed = $service->getAllowedGenders();
                    if (count($allowed) === 1) {
                        $gender = $allowed[0];
                    }
                }

                if ($gender) {
                    $workerQuery->where('gender', strtolower($gender));
                }

                $workerExists = $workerQuery->exists();

                if (! $workerExists) {
                    throw new \Exception(__('messages.invalid_worker_for_service'), 422);
                }

                $conflictService = app(\App\Service\Booking\BookingScheduleConflictService::class);
                $worker = \App\Models\Worker::find($workerId);
                foreach ($dates as $date) {
                    $conflictErr = $conflictService->checkWorkerConflict($worker, $date, $times['start_str'], $times['end_str'], null, true);
                    if ($conflictErr) {
                        throw new \Exception($conflictErr, 422);
                    }
                }
            }
        }

        // 6. Calculate prices
        $options = $data['options'] ?? $data['selected_options'] ?? [];
        $pricingWorkerCount = !empty($workerIds) ? count($workerIds) : ($data['workers_count'] ?? $service->min_workers ?? 1);
        $pricing = $this->calculatePricing(
            $service,
            $address,
            $pricingWorkerCount,
            count($dates),
            $data['visit_type'] ?? 'single_visit',
            $data['coupon_code'] ?? null,
            $user,
            $times['start_str'],
            $times['end_str'],
            (int) ($data['number_of_persons'] ?? 1),
            $options
        );
        $totalAmount = $pricing['pricing']['total'];

        // 7. Verify user payment balance (points or wallet)
        $paymentMethod = $data['payment_method'];
        $this->verifyPaymentBalance($user, $paymentMethod, $totalAmount, $service);

        // 8. Run actual database transaction for insertion
        DB::beginTransaction();
        try {
            // Lock database records to guard against parallel transactions (if workers provided)
            if (!empty($workerIds)) {
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
            }

            $teamId = $data['team_id'] ?? null;
            if ($service->isTeamBased()) {
                if (! $teamId && ! empty($workerIds)) {
                    $firstWorker = Worker::find($workerIds[0]);
                    if ($firstWorker && $firstWorker->team_id) {
                        $teamId = $firstWorker->team_id;
                    } else {
                        $matchedTeam = Team::whereHas('workers', function ($wq) use ($workerIds) {
                            $wq->whereIn('workers.id', $workerIds);
                        })->first();
                        if ($matchedTeam) {
                            $teamId = $matchedTeam->id;
                        }
                    }
                }

                if (!$teamId) {
                    $conflictService = app(\App\Service\Booking\BookingScheduleConflictService::class);
                    $teams = $service->teams()->where('status', 'active')->with('workers')->get();
                    foreach ($teams as $team) {
                        $teamAvailableAllDates = true;
                        foreach ($dates as $date) {
                            if (!$conflictService->isTeamAvailable($team, $date, $times['start_str'], $times['end_str'])) {
                                $teamAvailableAllDates = false;
                                break;
                            }
                        }
                        if ($teamAvailableAllDates && $team->workers->isNotEmpty()) {
                            $teamId = $team->id;
                            break;
                        }
                    }
                }

                if (empty($teamId)) {
                    throw new \Exception(__('messages.no_teams_available') ?? 'لا يوجد فريق عمل متاح لهذا الموعد.', 422);
                }

                $teamModel = Team::with('workers')->find($teamId);
                if (!$teamModel || $teamModel->workers->isEmpty()) {
                    throw new \Exception('فريق العمل المحدد لا يحتوي على عمالة مسندة.', 422);
                }

                if (empty($workerIds)) {
                    $workerIds = $teamModel->workers->pluck('id')->toArray();
                }
            } else {
                $teamId = null;
            }

            // Build snapshot of selected_options for team-based services
            $selectedOptions = null;
            if (! empty($options) && is_array($options)) {
                $selectedOptions = [];
                foreach ($options as $optItem) {
                    $optId = $optItem['id'] ?? null;
                    $qty = (int) ($optItem['quantity'] ?? 1);
                    if ($optId && $qty > 0) {
                        $optModel = ServiceOption::where('service_id', $service->id)->find($optId);
                        if ($optModel) {
                            $unitPrice = (float) $optModel->unit_price;
                            $selectedOptions[] = [
                                'id' => $optModel->id,
                                'name_ar' => $optModel->name_ar,
                                'name_en' => $optModel->name_en,
                                'unit_price' => $unitPrice,
                                'quantity' => $qty,
                                'total_price' => round($unitPrice * $qty, 2),
                            ];
                        }
                    }
                }
            }

            if ($service->service_code === 'PEDICURE_MANICURE' && empty($selectedOptions)) {
                $numberOfPersons = (int) ($data['number_of_persons'] ?? 1);
                $selectedOptions = [
                    'service_type' => 'PEDICURE_MANICURE',
                    'person_price' => (float) $service->base_price,
                    'person_duration' => $this->getPedicurePersonDuration($service),
                    'worker_allowed_hours' => $this->getPedicureWorkerAllowedHours($service),
                    'number_of_persons' => $numberOfPersons,
                    'persons_distribution' => $this->calculatePedicurePersonsDistribution($numberOfPersons, count($workerIds)),
                ];
            }

            // Create Booking (using first date as parent reference)
            $booking = Booking::create([
                'booking_ref' => 'HC-'.rand(100000, 999999),
                'customer_id' => $user->id,
                'service_id' => $data['service_id'],
                'address_id' => $data['address_id'],
                'worker_id' => !empty($workerIds) ? $workerIds[0] : null,
                'team_id' => $teamId,
                'coupon_id' => $pricing['coupon'] ? $pricing['coupon']->id : null,
                'booking_date' => $dates[0],
                'start_time' => $times['start_str'],
                'end_time' => $times['end_str'],
                'duration_minutes' => $times['duration_minutes'],
                'worker_gender' => $data['worker_gender'] ?? null,
                'number_of_workers' => !empty($workerIds) ? count($workerIds) : ($data['workers_count'] ?? $service->min_workers ?? 1),
                'number_of_children' => $service->service_code === 'BABYSITTER_HOURLY' ? ($data['number_of_children'] ?? null) : null,
                'number_of_persons' => $service->service_code === 'PEDICURE_MANICURE' ? ($data['number_of_persons'] ?? 1) : null,
                'child_age_groups' => $service->service_code === 'BABYSITTER_HOURLY' ? ($data['child_age_groups'] ?? null) : null,
                'selected_options' => $selectedOptions,
                'customer_notes' => $data['note'] ?? null,
                'price_subtotal' => $pricing['pricing']['subtotal'],
                'tax_amount' => $pricing['pricing']['tax'],
                'discount_amount' => $pricing['pricing']['discount'],
                'total_amount' => $totalAmount,
                'status' => 'Upcoming',
                'payment_status' => 'paid',
                'payment_method' => $paymentMethod,
                'paid_points' => $paymentMethod === 'points' ? $this->calculateRequiredPoints($service, $totalAmount) : 0,
                'visit_type' => $data['visit_type'] ?? 'single_visit',
                'visit_number' => count($dates),
                'payment_transaction_id' => 'TXN-'.rand(10000000, 99999999),
                'currency_code' => 'EGP',
                'contract_id' => 1,
            ]);

            // Create Booking Visits (One row per scheduled date)
            foreach ($dates as $date) {
                BookingVisit::create([
                    'booking_id' => $booking->id,
                    'visit_date' => $date,
                    'start_time' => $times['start_str'],
                    'end_time' => $times['end_str'],
                    'status' => 'Upcoming',
                ]);
            }

            // Deduct funds
            if ($paymentMethod === 'points') {
                $pointsRequired = $this->calculateRequiredPoints($service, $totalAmount);
                $user->pointTransactions()->create([
                    'type' => 'redeem',
                    'amount' => $pointsRequired,
                    'title_ar' => 'دفع مقابل حجز برقم مرجعي: '.$booking->booking_ref,
                    'title_en' => 'Payment for Booking Ref: '.$booking->booking_ref,
                ]);
            } elseif ($paymentMethod === 'wallet') {
                $user->withdraw($totalAmount, 'Payment - Booking', $booking->booking_ref);
            }

            // Create Worker Assignments
            foreach ($workerIds as $workerId) {
                BookingAssignment::create([
                    'booking_id' => $booking->id,
                    'worker_id' => $workerId,
                    'status' => 'active',
                    'assigned_at' => now(),
                ]);
            }

            if ($pricing['coupon']) {
                $pricing['coupon']->increment('used_count');
            }

            BookingHistory::create([
                'booking_id' => $booking->id,
                'new_status' => 'Upcoming',
                'note' => "تم إنشاء الحجز والدفع بنجاح عبر ({$paymentMethod}). إجمالي المبلغ: {$totalAmount} جنيه.",
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

        if (! $addressId) {
            $address = CustomerAddress::where('user_id', $user->id)
                ->where('is_active', true)
                ->first();

            if (! $address) {
                throw ValidationException::withMessages([
                    'address_id' => 'Please add or select a service address first.',
                ]);
            }
        } else {
            $address = CustomerAddress::with('serviceZone')
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->findOrFail($addressId);
        }

        $service = Service::findOrFail($data['service_id']);

        $isCovered = false;
        if ($address->service_zone_id) {
            $isCovered = $service->serviceZones()
                ->where('service_zones.id', $address->service_zone_id)
                ->where('service_zones.status', 'active')
                ->exists();
        }

        if (! $isCovered) {
            throw ValidationException::withMessages([
                'address_id' => __('messages.service_not_available_in_location') ?? 'هذه الخدمة غير متوفرة في موقعك الحالي.',
            ]);
        }

        return [$address, $service];
    }

    public function calculateBookingTimes(Service $service, string $date, string $startTime, ?int $customDuration = null): array
    {
        $periodHours = $customDuration ?: ($service->min_duration ?: ($service->default_duration ?? 2));
        $durationMinutes = $periodHours * 60;

        $start = Carbon::parse($startTime);
        $end = $start->copy()->addMinutes($durationMinutes);

        return [
            'start' => $start,
            'end' => $end,
            'start_str' => $start->format('H:i:s'),
            'end_str' => $end->format('H:i:s'),
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
            ->where('end_time', '>=', $startStr)
            ->exists();

        if (! $hasValidShift) {
            throw ValidationException::withMessages([
                'start_time' => __('validation.create_booking.shift_invalid'),
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
        $allWorkerIds = $service->workers()->where('status', 'active')->pluck('workers.id')->toArray();
        $conflictService = app(\App\Service\Booking\BookingScheduleConflictService::class);
        $dbBusyIds = $conflictService->getBusyWorkerIdsForSlot($allWorkerIds, $bookingDate, $startStr, $endStr, null, true);

        $cacheBusyIds = [];
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
    private function allocateWorkers($availableWorkers, int $workersCount, array $preferredInput): Collection
    {
        $allocated = collect();

        // Match preferred workers first
        if (! empty($preferredInput)) {
            $preferredAvailable = $availableWorkers->whereIn('id', $preferredInput)->values();
            $allocated = $allocated->merge($preferredAvailable);
        }

        $needed = $workersCount - $allocated->count();
        if ($needed > 0) {
            $remaining = $availableWorkers->whereNotIn('id', $allocated->pluck('id'))->values();
            if ($remaining->count() < $needed) {
                throw ValidationException::withMessages([
                    'workers_count' => __('messages.no_workers_available') ?? 'لا توجد عمالة كافية ومتاحة لهذا الموعد.',
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
    public function calculatePricing(Service $service, CustomerAddress $address, int $workersCount, int $visitsCount, string $visitType, ?string $couponCode = null, $user = null, $startTime = null, $endTime = null, int $numberOfPersons = 1, array $options = []): array
    {
        $pricePerPeriod = (float) ($service->base_price ?? 0);
        $isFirstService = ($service->service_code === 'DOMESTIC_HELPER_HOURLY');
        $isPedicureManicure = ($service->service_code === 'PEDICURE_MANICURE');
        $isDeepCleaning = ($service->service_code === 'DEEP_CLEANING');
        $isTeamService = $service->isTeamBased();
        $isSpecialized = in_array(strtoupper($service->service_code ?? ''), array_map('strtoupper', Service::getTeamBasedCodes()), true);

        if (($isTeamService || $isSpecialized) && ! empty($options)) {
            $optionsSubtotal = 0;
            foreach ($options as $item) {
                $optionId = $item['id'] ?? null;
                $qty = (int) ($item['quantity'] ?? 1);
                if ($optionId && $qty > 0) {
                    $opt = ServiceOption::where('service_id', $service->id)->find($optionId);
                    if ($opt) {
                        $optionsSubtotal += ((float) $opt->unit_price) * $qty;
                    }
                }
            }
            $subtotal = $optionsSubtotal * $visitsCount;
            $pricePerPeriod = $optionsSubtotal;
        } elseif ($isPedicureManicure) {
            $subtotal = $pricePerPeriod * max(1, $numberOfPersons);
        } elseif ($isDeepCleaning || $isTeamService || ($isSpecialized && $service->pricing_type === 'per_service')) {
            $subtotal = $pricePerPeriod * $visitsCount;
        } elseif ($service->pricing_type === 'per_hour' && ! $isFirstService && $startTime && $endTime) {
            $start = Carbon::parse($startTime);
            $end = Carbon::parse($endTime);
            if ($end->lt($start)) {
                $end->addDay();
            }
            $hours = $start->diffInHours($end);
            $subtotal = $pricePerPeriod * $hours * $visitsCount * $workersCount;
        } else {
            $subtotal = $pricePerPeriod * $visitsCount * $workersCount;
        }

        // Resolve Service Discount (from service->available_visit_types)
        $discountPercent = 0;
        $availableTypes = $service->available_visit_types ?? [];
        foreach ($availableTypes as $vt) {
            $vtType = $vt['type'] ?? '';
            if (
                $vtType === $visitType
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

        if (! empty($couponCode)) {
            $trimmedCode = trim($couponCode);
            $coupon = Coupon::whereRaw('LOWER(code) = ?', [strtolower($trimmedCode)])->first();

            if (! $coupon) {
                throw new \Exception(__('messages.coupon_not_found'), 422);
            }

            $errCode = $coupon->checkEligibility($user, $service, $visitType, $startTime ?? '00:00', $endTime ?? '00:00', $address, (int) ($workersCount ?? 1));
            if ($errCode) {
                throw new \Exception(__('messages.'.$errCode), 422);
            }

            if ($coupon->type === 'fixed') {
                $couponDiscountAmount = (float) $coupon->value;
            } elseif ($coupon->type === 'percentage') {
                $couponDiscountAmount = $amountAfterServiceDiscount * ($coupon->value / 100);
            }
            if ($couponDiscountAmount > $amountAfterServiceDiscount) {
                $couponDiscountAmount = $amountAfterServiceDiscount;
            }
        }

        $companyInfo = CompanyInfo::first();
        $vatPercentage = $companyInfo ? (float) $companyInfo->vat_percentage : 0;

        $totalDiscount = $serviceDiscountAmount + $couponDiscountAmount;
        $discountedAmount = $subtotal - $totalDiscount;
        if ($discountedAmount < 0) {
            $discountedAmount = 0;
        }

        $tax = $discountedAmount * ($vatPercentage / 100);
        $total = $discountedAmount + $tax;

        $pointsRequired = $this->calculateRequiredPoints($service, $total);

        return [
            'coupon' => $coupon,
            'pricing' => [
                'price_per_period' => $pricePerPeriod,
                'workers_count' => $workersCount,
                'visits_count' => $visitsCount,
                'subtotal' => $subtotal,
                'service_discount_amount' => $serviceDiscountAmount,
                'coupon_discount_amount' => $couponDiscountAmount,
                'discount' => $totalDiscount,
                'tax' => $tax,
                'total' => $total,
                'currency' => 'EGP',
                'loyalty_points' => [
                    'redemption_enabled' => (bool) $service->points_redemption_enabled,
                    'redemption_method' => $service->points_redemption_method,
                    'points_required' => $pointsRequired,
                ],
            ],
        ];
    }

    public function calculateRequiredPoints(Service $service, float $totalAmount): int
    {
        if (!$service->points_redemption_enabled) {
            return 0;
        }

        if ($service->points_redemption_method === 'fixed') {
            return (int) $service->points_required_fixed;
        }

        $rate = $service->points_conversion_rate ?? 10;

        return (int) ceil($totalAmount * $rate);
    }

    private function verifyPaymentBalance($user, string $paymentMethod, float $totalAmount, Service $service): void
    {
        if ($paymentMethod === 'points') {
            if (!$service->points_redemption_enabled) {
                throw new \Exception(__('messages.points_payment_disabled'), 422);
            }

            $userPointsBalance = \App\Models\PointTransaction::getUserPointsBalance($user->id);
            $pointsRequired = $this->calculateRequiredPoints($service, $totalAmount);

            if ($userPointsBalance < $pointsRequired) {
                throw new \Exception(__('messages.insufficient_points', ['current' => $userPointsBalance, 'required' => $pointsRequired]), 422);
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
            $holds = collect($holds)->filter(fn ($h) => now()->timestamp < $h['expires_at'])->toArray();

            foreach ($dates as $date) {
                $holds[] = [
                    'date' => $date,
                    'start_time' => $startStr,
                    'end_time' => $endStr,
                    'expires_at' => now()->addMinutes(5)->timestamp,
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
                ->filter(fn ($h) => now()->timestamp < $h['expires_at'])
                ->filter(fn ($h) => ! (in_array($h['date'], $dates) && $h['start_time'] === $startStr && $h['end_time'] === $endStr))
                ->toArray();

            if (empty($holds)) {
                Cache::forget("temp_hold_worker_{$workerId}");
            } else {
                Cache::put("temp_hold_worker_{$workerId}", $holds, now()->addMinutes(15));
            }
        }
    }

    /**
     * Check if the customer already has an active booking for this service that is not completed or closed.
     */
    public function hasActiveCustomerBooking(int $userId, int $serviceId): bool
    {
        return Booking::where('customer_id', $userId)
            ->where('service_id', $serviceId)
            ->whereIn('status', ['Upcoming', 'inService'])
            ->exists();
    }

    /**
     * Check if the customer already has an active booking at the same date and overlapping shift.
     */
    private function hasOverlappingCustomerBooking(int $userId, array $dates, string $startStr, string $endStr, int $serviceId): bool
    {
        return Booking::where('customer_id', $userId)
            ->where('service_id', $serviceId)
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

    public function calculateMinimumBabysitters(Service $service, array $ageGroupIds): int
    {
        $validGroups = $service->childAgeGroups()->where('status', 'active')->get();
        $calculatedMinWorkers = 0;
        $groupedCounts = array_count_values($ageGroupIds);

        foreach ($groupedCounts as $groupId => $count) {
            $group = $validGroups->firstWhere('id', $groupId);
            if (! $group) {
                throw new \Exception(__('validation.create_booking.babysitter_calculation_not_configured'), 422);
            }

            $childrenCountRule = $group->children_count ?? 1;
            if ($childrenCountRule <= 0) {
                $childrenCountRule = 1;
            }
            $requiredWorkerCountRule = $group->required_worker_count ?? 1;

            $calculatedMinWorkers += (int) ceil($count / $childrenCountRule) * $requiredWorkerCountRule;
        }

        $totalChildren = count($ageGroupIds);
        if ($totalChildren >= 3) {
            $calculatedMinWorkers = max($calculatedMinWorkers, 2);
        }

        return $calculatedMinWorkers;
    }

    public function getBabysitterBreakdown(Service $service, array $ageGroupIds): string
    {
        $validGroups = $service->childAgeGroups()->where('status', 'active')->get();
        $groupedCounts = array_count_values($ageGroupIds);
        $breakdownParts = [];
        $isArabic = app()->getLocale() === 'ar';

        foreach ($groupedCounts as $groupId => $count) {
            $group = $validGroups->firstWhere('id', $groupId);
            if (! $group) {
                continue;
            }

            $childrenCountRule = $group->children_count ?? 1;
            $requiredWorkerCountRule = $group->required_worker_count ?? 1;

            if ($group->calculation_type === 'per_child') {
                $needed = $count * $requiredWorkerCountRule;
                if ($isArabic) {
                    $breakdownParts[] = "{$group->name_ar} ({$count} أطفال) يحتاج {$needed} مربيات";
                } else {
                    $breakdownParts[] = "{$group->name_en} ({$count} children) requires {$needed} workers";
                }
            } else {
                $needed = (int) ceil($count / $childrenCountRule) * $requiredWorkerCountRule;
                if ($isArabic) {
                    $breakdownParts[] = "{$group->name_ar} ({$count} أطفال) يحتاج {$needed} مربيات (بمعدل مربية لكل {$childrenCountRule} أطفال)";
                } else {
                    $breakdownParts[] = "{$group->name_en} ({$count} children) requires {$needed} workers (ratio: {$requiredWorkerCountRule} per {$childrenCountRule} children)";
                }
            }
        }

        return implode($isArabic ? ' ، ' : ', ', $breakdownParts);
    }

    public function getPedicureWorkerAllowedHours(Service $service): int
    {
        $allowed = (int) ($service->max_worker_hours ?? $service->max_duration ?? 8);
        return $allowed > 0 ? $allowed : 8;
    }

    public function getPedicurePersonDuration(Service $service): int
    {
        $duration = (int) ($service->default_duration ?? 2);
        return $duration > 0 ? $duration : 2;
    }

    public function calculatePedicureWorkerCapacity(Service $service): int
    {
        $allowedHours = $this->getPedicureWorkerAllowedHours($service);
        $personDuration = $this->getPedicurePersonDuration($service);
        return (int) max(1, floor($allowedHours / $personDuration));
    }

    public function calculateMinimumPedicureWorkers(Service $service, int $numberOfPersons = 1): int
    {
        $capacity = $this->calculatePedicureWorkerCapacity($service);
        $persons = max(1, $numberOfPersons);
        return (int) max(1, ceil($persons / $capacity));
    }

    public function calculatePedicureDuration(Service $service, int $numberOfPersons, int $workersCount): int
    {
        $personDuration = $this->getPedicurePersonDuration($service);
        $workers = max(1, $workersCount);
        $persons = max(1, $numberOfPersons);
        return (int) ($personDuration * ceil($persons / $workers));
    }

    public function calculatePedicurePersonsDistribution(int $numberOfPersons, int $workersCount): array
    {
        $workers = max(1, $workersCount);
        $persons = max(1, $numberOfPersons);
        $base = intdiv($persons, $workers);
        $remainder = $persons % $workers;

        $distribution = [];
        for ($i = 0; $i < $workers; $i++) {
            $distribution[] = $base + ($i < $remainder ? 1 : 0);
        }
        return $distribution;
    }

    public function determinePedicureActualMinWorkers(
        Service $service,
        array $dates,
        string $startTime,
        int $numberOfPersons,
        int $theoreticalMin,
        int $maxWorkers
    ): array {
        $maxCandidate = min($numberOfPersons, $maxWorkers);
        for ($w = $theoreticalMin; $w <= $maxCandidate; $w++) {
            $duration = $this->calculatePedicureDuration($service, $numberOfPersons, $w);
            $times = $this->calculateBookingTimes($service, $dates[0], $startTime, $duration);
            $busyIds = $this->getBusyWorkerIdsForDates($service, $dates, $times['start_str'], $times['end_str']);
            $availableWorkersCount = $service->workers()
                ->where('workers.status', 'active')
                ->where('workers.gender', 'female')
                ->whereNotIn('workers.id', $busyIds)
                ->count();

            if ($availableWorkersCount >= $w) {
                return [
                    'workers_count' => $w,
                    'duration' => $duration,
                ];
            }
        }

        return [
            'workers_count' => $theoreticalMin,
            'duration' => $this->calculatePedicureDuration($service, $numberOfPersons, $theoreticalMin),
        ];
    }
}
