<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\StoreBookingRequest as StoreBookingRequestV2;
use App\Models\Booking;
use App\Models\CompanyInfo;
use App\Models\CustomerAddress;
use App\Models\Service;
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
        $times = $this->bookingService->calculateBookingTimes($service, $dates[0], $request->start_time, $request->duration);

        $pricingResult = $this->bookingService->calculatePricing(
            $service,
            $address,
            count($request->worker_ids),
            count($dates),
            $request->visit_type ?? 'single_visit',
            $request->coupon_code,
            $user,
            $times['start_str'],
            $times['end_str']
        );

        $duration = (float) ($request->duration ?? $service->default_duration ?? 4);
        $workers = Worker::whereIn('id', $request->worker_ids ?? [])->get();

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
            $request->child_age_groups ?? []
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
        $booking->load(['service.category', 'service.rules', 'address', 'customer', 'workers', 'visits']);

        $service = $booking->service;
        $address = $booking->address;
        $company = CompanyInfo::first();

        $dates = $booking->visits->pluck('visit_date')->toArray();
        if (empty($dates)) {
            $dates = [$booking->booking_date];
        }

        $durationHours = $booking->duration_minutes ? ($booking->duration_minutes / 60) : 4;

        $pricing = [
            'subtotal' => (float) $booking->price_subtotal,
            'discount' => (float) $booking->discount_amount,
            'tax' => (float) $booking->tax_amount,
            'total' => (float) $booking->total_amount,
        ];

        $contractRef = 'CON-'.$booking->created_at->format('Y').'-'.str_pad($booking->id, 6, '0', STR_PAD_LEFT);

        return $this->renderContractPdf(
            $lang,
            $booking->customer,
            $service,
            $address,
            $company,
            $dates,
            $booking->start_time,
            (float) $durationHours,
            $booking->workers,
            $pricing,
            $booking->payment_method,
            $booking->booking_ref,
            $contractRef,
            $booking->created_at->format('Y-m-d h:i A'),
            $booking->worker_gender,
            $booking->visit_type ?? 'single_visit',
            (int) $booking->number_of_children,
            $booking->child_age_groups ?? []
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
        array $childAgeGroups
    ) {
        // Customer Address formatting
        $addressParts = [];
        if ($address) {
            $city = $address->city;
            $area = $address->area;

            if ($city) {
                $addressParts[] = $lang === 'ar' ? ($city->name_ar ?? '') : ($city->name_en ?? '');
            }
            if ($area) {
                $addressParts[] = $lang === 'ar' ? ($area->name_ar ?? '') : ($area->name_en ?? '');
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
            'numberOfChildren' => $numberOfChildren,
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
