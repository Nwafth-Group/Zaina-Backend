<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\StoreBookingRequest as StoreBookingRequestV2;
use App\Models\Booking;
use App\Models\CompanyInfo;
use App\Models\CustomerAddress;
use App\Models\Service;
use App\Models\ServiceOption;
use App\Models\Team;
use App\Models\Worker;
use App\Service\Customer\CustomerBookingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf;

class BookingContractController extends Controller
{
    public function __construct(private CustomerBookingService $bookingService) {}

    /**
     * Generate an on-the-fly PDF preview of the Service Booking Contract during checkout.
     */
    public function previewContract(StoreBookingRequestV2 $request)
    {
        $user = auth()->user();
        $lang = $request->header('Accept-Language') === 'ar' || app()->getLocale() === 'ar' ? 'ar' : 'en';

        $service = Service::with('category', 'rules')->find($request->service_id);
        $address = $request->address_id ? CustomerAddress::find($request->address_id) : null;
        $company = CompanyInfo::first();

        $dates = $request->dates ?? [];
        $optionsInput = $request->input('options', $request->input('selected_options', [])) ?? [];
        $numberOfPersons = (int) ($request->number_of_persons ?? 1);
        $workerIds = $request->worker_ids ?? [];
        $workersCount = !empty($workerIds) ? count($workerIds) : ($service->min_workers ?? 1);

        $duration = (float) ($request->duration ?? $service->default_duration ?? 4);
        if ($service->service_code === 'PEDICURE_MANICURE') {
            $duration = (float) $this->bookingService->calculatePedicureDuration($service, $numberOfPersons, $workersCount);
        }

        $times = $this->bookingService->calculateBookingTimes($service, $dates[0], $request->start_time, (int) $duration);

        $pricingResult = $this->bookingService->calculatePricing(
            $service,
            $address,
            $workersCount,
            count($dates),
            $request->visit_type ?? 'single_visit',
            $request->coupon_code,
            $user,
            $times['start_str'],
            $times['end_str'],
            $numberOfPersons,
            $optionsInput
        );

        $workers = Worker::whereIn('id', $workerIds)->get();

        $team = null;
        if ($request->team_id) {
            $team = Team::with('workers')->find($request->team_id);
            if ($workers->isEmpty() && $team && $team->workers->isNotEmpty()) {
                $workers = $team->workers;
            }
        } elseif ($service->isTeamBased() && $workers->isNotEmpty()) {
            $firstWorker = $workers->first();
            if ($firstWorker && $firstWorker->team_id) {
                $team = Team::with('workers')->find($firstWorker->team_id);
            } else {
                $team = Team::whereHas('workers', function ($wq) use ($workerIds) {
                    $wq->whereIn('workers.id', $workerIds);
                })->with('workers')->first();
            }
        }

        $selectedOptionsSnapshot = [];
        if (!empty($optionsInput) && is_array($optionsInput)) {
            foreach ($optionsInput as $optItem) {
                $optId = $optItem['id'] ?? null;
                $qty = (int) ($optItem['quantity'] ?? 1);
                if ($optId && $qty > 0) {
                    $optModel = ServiceOption::where('service_id', $service->id)->find($optId);
                    if ($optModel) {
                        $uPrice = (float) $optModel->unit_price;
                        $selectedOptionsSnapshot[] = [
                            'id' => $optModel->id,
                            'name_ar' => $optModel->name_ar,
                            'name_en' => $optModel->name_en,
                            'unit_price' => $uPrice,
                            'quantity' => $qty,
                            'total_price' => round($uPrice * $qty, 2),
                        ];
                    }
                }
            }
        }

        $pricing = [
            'subtotal' => (float) $pricingResult['pricing']['subtotal'],
            'discount' => (float) $pricingResult['pricing']['discount'],
            'tax' => (float) $pricingResult['pricing']['tax'],
            'total' => (float) $pricingResult['pricing']['total'],
        ];

        return $this->renderContractPdf(
            $lang,
            $user,
            $service,
            $address,
            $company,
            $dates,
            $request->start_time,
            $duration,
            $workers,
            $pricing,
            $request->payment_method,
            'BKG-2026-'.rand(100000, 999999),
            'CON-2026-'.rand(100000, 999999),
            Carbon::now()->format('Y-m-d h:i A'),
            $request->worker_gender,
            $request->visit_type ?? 'single_visit',
            (int) $request->number_of_children,
            $request->child_age_groups ?? [],
            $numberOfPersons,
            $selectedOptionsSnapshot,
            $team
        );
    }

    /**
     * Download the service contract PDF for an existing booking.
     */
    public function downloadContract(Booking $booking)
    {
        $user = auth()->user();
        abort_if((int) $booking->customer_id !== (int) $user->id, 403, 'Unauthorized access to contract.');

        $lang = request()->header('Accept-Language') === 'ar' || app()->getLocale() === 'ar' ? 'ar' : 'en';
        $booking->load(['service.category', 'service.rules', 'address', 'customer', 'workers', 'visits', 'team.workers']);

        $service = $booking->service;
        $address = $booking->address;
        $company = CompanyInfo::first();

        $dates = $booking->visits->pluck('visit_date')->toArray();
        if (empty($dates)) {
            $dates = [$booking->booking_date];
        }

        $durationHours = $booking->duration_minutes ? ($booking->duration_minutes / 60) : ($service->default_duration ?? 4);

        $pricing = [
            'subtotal' => (float) $booking->price_subtotal,
            'discount' => (float) $booking->discount_amount,
            'tax' => (float) $booking->tax_amount,
            'total' => (float) $booking->total_amount,
        ];

        $contractRef = 'CON-'.$booking->created_at->format('Y').'-'.str_pad($booking->id, 6, '0', STR_PAD_LEFT);

        $selectedOptions = is_array($booking->selected_options)
            ? $booking->selected_options
            : json_decode($booking->selected_options ?? '[]', true);

        $workers = $booking->workers;
        if ($workers->isEmpty() && $booking->team && $booking->team->workers->isNotEmpty()) {
            $workers = $booking->team->workers;
        }

        return $this->renderContractPdf(
            $lang,
            $booking->customer,
            $service,
            $address,
            $company,
            $dates,
            $booking->start_time,
            (float) $durationHours,
            $workers,
            $pricing,
            $booking->payment_method ?? 'wallet',
            $booking->booking_ref,
            $contractRef,
            $booking->created_at->format('Y-m-d h:i A'),
            $booking->worker_gender,
            $booking->visit_type ?? 'single_visit',
            (int) $booking->number_of_children,
            $booking->child_age_groups ?? [],
            (int) ($booking->number_of_persons ?? 1),
            $selectedOptions ?? [],
            $booking->team
        );
    }

    /**
     * Show contract PDF publicly via secure signed URL.
     */
    public function viewContractPublicly(\Illuminate\Http\Request $request, Booking $booking)
    {
        abort_unless($request->hasValidSignature(), 401, 'Invalid or expired signature.');

        $lang = $request->input('lang') === 'ar' || app()->getLocale() === 'ar' ? 'ar' : 'en';
        $booking->load(['service.category', 'service.rules', 'address', 'customer', 'workers', 'visits', 'team.workers']);

        $service = $booking->service;
        $address = $booking->address;
        $company = CompanyInfo::first();

        $dates = $booking->visits->pluck('visit_date')->toArray();
        if (empty($dates)) {
            $dates = [$booking->booking_date];
        }

        $durationHours = $booking->duration_minutes ? ($booking->duration_minutes / 60) : ($service->default_duration ?? 4);

        $pricing = [
            'subtotal' => (float) $booking->price_subtotal,
            'discount' => (float) $booking->discount_amount,
            'tax' => (float) $booking->tax_amount,
            'total' => (float) $booking->total_amount,
        ];

        $contractRef = 'CON-'.$booking->created_at->format('Y').'-'.str_pad($booking->id, 6, '0', STR_PAD_LEFT);

        $selectedOptions = is_array($booking->selected_options)
            ? $booking->selected_options
            : json_decode($booking->selected_options ?? '[]', true);

        $workers = $booking->workers;
        if ($workers->isEmpty() && $booking->team && $booking->team->workers->isNotEmpty()) {
            $workers = $booking->team->workers;
        }

        return $this->renderContractPdf(
            $lang,
            $booking->customer,
            $service,
            $address,
            $company,
            $dates,
            $booking->start_time,
            (float) $durationHours,
            $workers,
            $pricing,
            $booking->payment_method ?? 'wallet',
            $booking->booking_ref,
            $contractRef,
            $booking->created_at->format('Y-m-d h:i A'),
            $booking->worker_gender,
            $booking->visit_type ?? 'single_visit',
            (int) $booking->number_of_children,
            $booking->child_age_groups ?? [],
            (int) ($booking->number_of_persons ?? 1),
            $selectedOptions ?? [],
            $booking->team
        );
    }

    /**
     * Download the service contract PDF for admin access (skips customer ownership constraint).
     */
    public function downloadContractForAdmin(Booking $booking)
    {
        $lang = request()->header('Accept-Language') === 'ar' || app()->getLocale() === 'ar' ? 'ar' : 'en';
        $booking->load(['service.category', 'service.rules', 'address', 'customer', 'workers', 'visits', 'team.workers']);

        $service = $booking->service;
        $address = $booking->address;
        $company = CompanyInfo::first();

        $dates = $booking->visits->pluck('visit_date')->toArray();
        if (empty($dates)) {
            $dates = [$booking->booking_date];
        }

        $durationHours = $booking->duration_minutes ? ($booking->duration_minutes / 60) : ($service->default_duration ?? 4);

        $pricing = [
            'subtotal' => (float) $booking->price_subtotal,
            'discount' => (float) $booking->discount_amount,
            'tax' => (float) $booking->tax_amount,
            'total' => (float) $booking->total_amount,
        ];

        $contractRef = 'CON-'.$booking->created_at->format('Y').'-'.str_pad($booking->id, 6, '0', STR_PAD_LEFT);

        $selectedOptions = is_array($booking->selected_options)
            ? $booking->selected_options
            : json_decode($booking->selected_options ?? '[]', true);

        $workers = $booking->workers;
        if ($workers->isEmpty() && $booking->team && $booking->team->workers->isNotEmpty()) {
            $workers = $booking->team->workers;
        }

        return $this->renderContractPdf(
            $lang,
            $booking->customer,
            $service,
            $address,
            $company,
            $dates,
            $booking->start_time,
            (float) $durationHours,
            $workers,
            $pricing,
            $booking->payment_method ?? 'wallet',
            $booking->booking_ref,
            $contractRef,
            $booking->created_at->format('Y-m-d h:i A'),
            $booking->worker_gender,
            $booking->visit_type ?? 'single_visit',
            (int) $booking->number_of_children,
            $booking->child_age_groups ?? [],
            (int) ($booking->number_of_persons ?? 1),
            $selectedOptions ?? [],
            $booking->team
        );
    }

    /**
     * Render the unified contract layout as a PDF download/stream.
     */
    private function renderContractPdf(
        string $lang,
        $customerUser,
        Service $service,
        ?CustomerAddress $address,
        ?CompanyInfo $company,
        array $dates,
        string $startTime,
        float $durationHours,
        $workers,
        array $pricing,
        string $paymentMethod,
        string $bookingRef,
        string $contractRef,
        string $contractDate,
        ?string $workerGender,
        string $visitType,
        int $numberOfChildren,
        array $childAgeGroups,
        int $numberOfPersons = 1,
        array $selectedOptions = [],
        ?Team $team = null
    ) {
        // Customer Address formatting
        $addressParts = [];
        if ($address) {
            if ($address->formatted_address) {
                $addressParts[] = $address->formatted_address;
            } elseif ($address->serviceZone?->name) {
                $addressParts[] = $address->serviceZone->name;
            }
            if ($address->street) {
                $addressParts[] = $address->street;
            }
            if ($address->building_name) {
                $addressParts[] = $address->building_name;
            }
            if ($address->building_no) {
                $addressParts[] = $address->building_no;
            }
            if ($address->floor) {
                $addressParts[] = ($lang === 'ar' ? 'الطابق ' : 'Floor ').$address->floor;
            }
            if ($address->apartment_no) {
                $addressParts[] = ($lang === 'ar' ? 'شقة ' : 'Apt ').$address->apartment_no;
            }
        }
        $addressStr = ! empty($addressParts) ? implode(', ', array_filter($addressParts)) : ($lang === 'ar' ? 'العنوان المحدد من قبل العميل' : 'Client Designated Address');

        $customerData = [
            'name' => $customerUser->full_name ?? $customerUser->name ?? 'Guest User',
            'phone' => $customerUser->phone_number ?? '—',
            'email' => $customerUser->email ?? '—',
            'address' => $addressStr,
        ];

        // Company Details mapping
        $companyData = [
            'name' => $company ? ($lang === 'ar' ? $company->name_ar : $company->name_en) : 'Zeina Home Services',
            'address' => $company ? $company->website ?? 'Saudi Arabia' : 'Saudi Arabia',
            'tax_number' => $company ? $company->tax_number : '123456789000003',
            'phone' => $company ? $company->phone : '920000000',
        ];

        // Service & Booking mapping
        $isBabysitter = ($service->service_code === 'BABYSITTER_HOURLY');

        if ($visitType === 'single_visit') {
            $visitTypeFormatted = ($lang === 'ar' ? 'زيارة واحدة' : 'Single Visit');
        } else {
            $visitTypeFormatted = ($lang === 'ar' ? '3 زيارات في الأسبوع' : '3 Visits per Week');
        }

        $childAgeGroupsList = [];
        if ($isBabysitter && ! empty($childAgeGroups)) {
            $ageGroups = $service->childAgeGroups()->whereIn('id', $childAgeGroups)->get();
            foreach ($childAgeGroups as $groupId) {
                $group = $ageGroups->firstWhere('id', $groupId);
                if ($group) {
                    $childAgeGroupsList[] = $lang === 'ar' ? $group->name_ar : $group->name_en;
                }
            }
        }

        // Shift start/end times
        $start = Carbon::parse($startTime);
        $end = $start->copy()->addHours($durationHours);
        $shiftTime = sprintf('%s - %s', $start->format('h:i A'), $end->format('h:i A'));

        // Allocated Workers mapping
        $allocatedWorkers = [];
        foreach ($workers as $w) {
            $allocatedWorkers[] = [
                'name' => $w->name,
                'gender' => $w->gender,
                'nationality' => $w->nationality,
                'id_number' => $w->id_number,
            ];
        }

        // Policies & Rules mapping
        $rules = $service->rules;
        $rulesData = [
            'allow_cancel' => $rules ? (bool) $rules->allow_cancel : true,
            'free_cancel_cutoff_hours' => $rules ? $rules->free_cancel_cutoff_hours : 24,
            'allow_reschedule' => $rules ? (bool) $rules->allow_reschedule : true,
            'reschedule_cutoff_hours' => $rules ? $rules->reschedule_cutoff_hours : 24,
        ];

        // Dynamic terms & conditions mapping
        $termsText = $company ? ($lang === 'ar' ? $service->terms_ar : $service->terms_en) : '';

        // Localize payment method values
        $paymentMethodLabel = match ($paymentMethod) {
            'points' => ($lang === 'ar' ? 'نقاط مكافآت' : 'Reward Points'),
            'wallet' => ($lang === 'ar' ? 'المحفظة الإلكترونية' : 'E-Wallet'),
            'card' => ($lang === 'ar' ? 'بطاقة ائتمان' : 'Credit Card'),
            default => $paymentMethod,
        };

        $workerGenderFormatted = null;
        if ($workerGender) {
            if (strtolower($workerGender) === 'female') {
                $workerGenderFormatted = ($lang === 'ar' ? 'أنثى' : 'Female');
            } elseif (strtolower($workerGender) === 'male') {
                $workerGenderFormatted = ($lang === 'ar' ? 'ذكر' : 'Male');
            } else {
                $workerGenderFormatted = $workerGender;
            }
        }

        // Resolve logo from CompanyInfo media collection
        $logoMedia = $company ? $company->media->where('collection', 'company_info/logo')->first() : null;
        $logoBase64 = null;
        $logoMime = 'image/png';
        if ($logoMedia) {
            $disk = $logoMedia->disk ?? 'public';
            $path = $logoMedia->path;
            if (Storage::disk($disk)->exists($path)) {
                $absPath = Storage::disk($disk)->path($path);
                $mimeType = $logoMedia->mime_type ?? (function_exists('mime_content_type') ? mime_content_type($absPath) : 'image/webp');

                if (str_contains($mimeType, 'webp')) {
                    // Convert WebP to PNG for mPDF compatibility using GD extension
                    if (function_exists('imagecreatefromwebp')) {
                        try {
                            $im = @imagecreatefromwebp($absPath);
                            if ($im) {
                                ob_start();
                                imagepng($im);
                                $pngData = ob_get_clean();
                                $logoBase64 = base64_encode($pngData);
                                $logoMime = 'image/png';
                                imagedestroy($im);
                            }
                        } catch (\Throwable $e) {
                            $logoBase64 = base64_encode(file_get_contents($absPath));
                            $logoMime = $mimeType;
                        }
                    } else {
                        $logoBase64 = base64_encode(file_get_contents($absPath));
                        $logoMime = $mimeType;
                    }
                } else {
                    $logoBase64 = base64_encode(file_get_contents($absPath));
                    $logoMime = $mimeType;
                }
            }
        }

        $pdfData = [
            'lang' => $lang,
            'bookingRef' => $bookingRef,
            'contractRef' => $contractRef,
            'contractDate' => $contractDate,
            'customer' => $customerData,
            'company' => $companyData,
            'serviceName' => $lang === 'ar' ? $service->name_ar : $service->name_en,
            'serviceCategory' => $service->category ? ($lang === 'ar' ? $service->category->name_ar : $service->category->name_en) : '—',
            'visitTypeFormatted' => $visitTypeFormatted,
            'isBabysitter' => $isBabysitter,
            'isPedicureManicure' => ($service->service_code === 'PEDICURE_MANICURE'),
            'isSpecialized' => in_array(strtoupper($service->service_code ?? ''), array_map('strtoupper', Service::getTeamBasedCodes()), true) || ((int)$service->id >= 6 && (int)$service->id <= 12) || !empty($selectedOptions),
            'isOfficeClinic' => (strtoupper($service->service_code ?? '') === 'OFFICE_CLINIC_CLEANING' || (int)$service->id === 12),
            'selectedOptions' => $selectedOptions,
            'team' => $team ? [
                'name' => $lang === 'ar' ? ($team->name_ar ?: $team->name_en) : ($team->name_en ?: $team->name_ar),
                'code' => $team->code,
            ] : null,
            'numberOfChildren' => $numberOfChildren,
            'numberOfPersons' => $numberOfPersons,
            'childAgeGroupsList' => $childAgeGroupsList,
            'basePrice' => (float) $service->base_price,
            'currency' => 'EGP',
            'dates' => $dates,
            'shiftTime' => $shiftTime,
            'durationHours' => $durationHours,
            'workersCount' => count($allocatedWorkers),
            'arrivalBuffer' => $rules ? $rules->arrival_buffer_minutes : 15,
            'allocatedWorkers' => $allocatedWorkers,
            'pricing' => $pricing,
            'vatPercentage' => $company ? (float) $company->vat_percentage : 0.00,
            'paymentMethod' => $paymentMethodLabel,
            'rules' => $rulesData,
            'terms' => $termsText,
            'logoBase64' => $logoBase64,
            'logoMime' => $logoMime,
            'serviceDescription' => $lang === 'ar' ? ($service->description_ar ?? '') : ($service->description_en ?? ''),
            'workerGender' => $workerGenderFormatted,
        ];

        $pdf = LaravelMpdf::loadView('pdf.booking_contract', $pdfData);

        return $pdf->stream('booking_contract_'.$bookingRef.'.pdf');
    }
}
