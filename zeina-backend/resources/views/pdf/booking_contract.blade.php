<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $lang === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <title>{{ $lang === 'ar' ? 'عقد حجز خدمة' : 'Service Booking Contract' }}</title>
    <style>
        @page {
            background-color: #FFF6F8;
            margin-top: 10mm;
            margin-bottom: 10mm;
            margin-left: 10mm;
            margin-right: 10mm;
        }

        body {
            font-family: 'cairo', sans-serif;
            font-size: 11pt;
            color: #333333;
            line-height: 1.5;
            background-color: #FFF6F8;
            margin: 0;
            padding: 0;
        }

        /* Header Info styling */
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: top;
            border: none;
        }

        .brand-title {
            font-size: 20pt;
            font-weight: bold;
            color: #181D22;
        }

        /* Main Section Container */
        .main-content-container {
            background-color: #ffffff;
            border-radius: 36px;
            padding: 28px;
        }

        /* Card Section layout (Items section) */
        .card {
            border: 1px solid #F45B8C;
            border-radius: 20px;
            background-color: #ffffff;
            margin-bottom: 22px;
            padding: 22px;
        }

        .card-header {
            background-color: transparent;
            color: #1F2937;
            font-weight: bold;
            font-size: 13pt;
            padding: 0;
            margin-bottom: 14px;
            border-bottom: none;
        }

        .card-body {
            padding: 0;
        }

        /* 2-Column Property Grid */
        .details-table {
            width: 100%;
            border-collapse: collapse;
        }

        .details-table td {
            width: 50%;
            padding: 8px 10px;
            vertical-align: top;
            font-size: 11pt;
            border: none;
        }

        .label {
            color: #777777;
            font-size: 10pt;
            font-weight: normal;
            margin-bottom: 3px;
        }

        .value {
            color: #1F2937;
            font-size: 11.5pt;
            font-weight: bold;
        }

        /* Policy List styling */
        .policy-list {
            width: 100%;
            border-collapse: collapse;
        }

        .policy-list td {
            padding: 10px;
            font-size: 11pt;
            border-bottom: 1px solid #fafafa;
        }

        .policy-list tr:last-child td {
            border-bottom: none;
        }

        .policy-desc {
            color: #555555;
        }

        .policy-val {
            text-align: {{ $lang === 'ar' ? 'left' : 'right' }};
            font-weight: bold;
            color: #F45B8C;
        }

        /* Terms List */
        .terms-box {
            padding: 10px 15px;
            font-size: 10pt;
            color: #555555;
            line-height: 1.6;
        }

        /* Text alignment helpers */
        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left;
        }

        .text-muted {
            color: #888888;
        }
    </style>
</head>

<body>

    <!-- Header Block Row 1: Title and Logo at same line -->
    <table class="header-table">
        <tr>
            <!-- Left Side (EN) / Right Side (AR) -->
            <td style="width: 60%; line-height: 1.6; vertical-align: middle;">
                <div class="brand-title">
                    {{ $lang === 'ar' ? 'عقد تقديم خدمات منزلية' : 'Service Booking Contract' }}
                </div>
                <div style="font-size: 12pt; color: #667085; font-weight: bold; margin-top: 6px;">
                    {{ $serviceName }} - {{ $visitTypeFormatted }}
                </div>
            </td>
            <!-- Right Side (EN) / Left Side (AR) -->
            <td style="width: 40%; text-align: {{ $lang === 'ar' ? 'left' : 'right' }}; vertical-align: middle;">
                @if(!empty($logoBase64))
                    <img src="data:{{ $logoMime }};base64,{{ $logoBase64 }}" style="max-height: 120px;" />
                @endif
            </td>
        </tr>
    </table>

    <div style="height: 30px;"></div>

    <!-- Header Block Row 2: Metadata references at same line -->
    <table class="header-table">
        <tr>
            <!-- Left Side (EN) / Right Side (AR) -->
            <td style="width: 50%; line-height: 1.6; vertical-align: top;">
                <div style="font-size: 11pt; color: #666666; margin-bottom: 4px;">
                    <strong>{{ $lang === 'ar' ? 'مرجع العقد:' : 'Contract Reference:' }}</strong> {{ $contractRef }}
                </div>
                <div style="font-size: 11pt; color: #666666;">
                    <strong>{{ $lang === 'ar' ? 'تاريخ العقد:' : 'Contract Date:' }}</strong> {{ $contractDate }}
                </div>
            </td>
            <!-- Right Side (EN) / Left Side (AR) -->
            <td style="width: 50%; text-align: {{ $lang === 'ar' ? 'left' : 'right' }}; line-height: 1.6; vertical-align: top;">
                <div style="font-size: 11pt; color: #666666; margin-bottom: 4px;">
                    <strong>{{ $lang === 'ar' ? 'مرجع الحجز:' : 'Booking Reference:' }}</strong> {{ $bookingRef }}
                </div>
                <div style="font-size: 11pt; color: #666666;">
                    <strong>{{ $lang === 'ar' ? 'نسخة العقد:' : 'Contract Version:' }}</strong> v1.0
                </div>
            </td>
        </tr>
    </table>

    <div style="border-bottom: 2.5px solid #F45B8C; margin-bottom: 35px; margin-top: 25px;"></div>

    <!-- Main Content Container with white background (#FFFFFF) wrapping all card items -->
    <div class="main-content-container">

        <!-- 1. Customer Information Card -->
        <div class="card">
            <div class="card-header">{{ $lang === 'ar' ? 'معلومات العميل' : 'Customer Information' }}</div>
            <div class="card-body">
                <table class="details-table">
                    <tr>
                        <td>
                            <div class="label">{{ $lang === 'ar' ? 'الاسم' : 'Name' }}</div>
                            <div class="value">{{ $customer['name'] }}</div>
                        </td>
                        <td>
                            <div class="label">{{ $lang === 'ar' ? 'رقم الجوال' : 'Mobile Number' }}</div>
                            <div class="value">{{ $customer['phone'] }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="label">{{ $lang === 'ar' ? 'البريد الإلكتروني' : 'Email' }}</div>
                            <div class="value">{{ $customer['email'] ?? '—' }}</div>
                        </td>
                        <td>
                            <div class="label">{{ $lang === 'ar' ? 'العنوان' : 'Address' }}</div>
                            <div class="value">{{ $customer['address'] }}</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- 2. Company Information Card -->
        <div class="card">
            <div class="card-header">{{ $lang === 'ar' ? 'معلومات الشركة' : 'Company Information' }}</div>
            <div class="card-body">
                <table class="details-table">
                    <tr>
                        <td>
                            <div class="label">{{ $lang === 'ar' ? 'اسم الشركة' : 'Company Name' }}</div>
                            <div class="value">{{ $company['name'] }}</div>
                        </td>
                        <td>
                            <div class="label">{{ $lang === 'ar' ? 'العنوان' : 'Address' }}</div>
                            <div class="value">{{ $company['address'] }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="label">{{ $lang === 'ar' ? 'الرقم الضريبي' : 'VAT / Tax Number' }}</div>
                            <div class="value">{{ $company['tax_number'] ?? '—' }}</div>
                        </td>
                        <td>
                            <div class="label">{{ $lang === 'ar' ? 'رقم الاتصال والدعم' : 'Support Contact' }}</div>
                            <div class="value">{{ $company['phone'] }}</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- 3. Service Details Card (Dynamic fields depending on service type) -->
        <div class="card">
            <div class="card-header">{{ $lang === 'ar' ? 'تفاصيل الخدمة' : 'Service Details' }}</div>
            <div class="card-body">
                <table class="details-table">
                    <tr>
                        <td>
                            <div class="label">{{ $lang === 'ar' ? 'اسم الخدمة' : 'Service Name' }}</div>
                            <div class="value">{{ $serviceName }}</div>
                        </td>
                        <td>
                            <div class="label">{{ $lang === 'ar' ? 'قسم الخدمة' : 'Service Category' }}</div>
                            <div class="value">{{ $serviceCategory }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="label">{{ $lang === 'ar' ? 'نوع الزيارة' : 'Visit Type' }}</div>
                            <div class="value">{{ $visitTypeFormatted }}</div>
                        </td>
                        <td>
                            @if($isBabysitter)
                                <div class="label">{{ $lang === 'ar' ? 'عدد الأطفال' : 'Number of Children' }}</div>
                                <div class="value">{{ $numberOfChildren }}</div>
                            @elseif(!empty($workerGender))
                                <div class="label">{{ $lang === 'ar' ? 'جنس العمالة المطلوبة' : 'Preferred Worker Gender' }}</div>
                                <div class="value">{{ $workerGender }}</div>
                            @else
                                &nbsp;
                            @endif
                        </td>
                    </tr>
                    @if($isBabysitter && !empty($workerGender))
                        <tr>
                            <td>
                                <div class="label">{{ $lang === 'ar' ? 'جنس العمالة المطلوبة' : 'Preferred Worker Gender' }}</div>
                                <div class="value">{{ $workerGender }}</div>
                            </td>
                            <td>
                                &nbsp;
                            </td>
                        </tr>
                    @endif
                    @if($isBabysitter && !empty($childAgeGroupsList))
                        <tr>
                            <td colspan="2" style="padding-top: 10px;">
                                <div class="label" style="font-size: 10pt; color: #777777;">{{ $lang === 'ar' ? 'الفئات العمرية للأطفال' : 'Children & Age Groups' }}</div>
                                <div class="value" style="font-weight: normal; font-size: 11pt; line-height: 1.4;">
                                    @foreach($childAgeGroupsList as $idx => $ageDesc)
                                        • {{ $lang === 'ar' ? 'الطفل' : 'Child' }} {{ $idx + 1 }}: {{ $ageDesc }}<br/>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td colspan="2" style="padding-top: 10px;">
                            <div class="label">{{ $lang === 'ar' ? 'وصف الخدمة' : 'Service Description' }}</div>
                            <div class="value" style="font-weight: normal; font-size: 11pt; line-height: 1.4;">
                                {!! nl2br(e($serviceDescription)) !!}
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- 4. Booking details Card -->
        <div class="card">
            <div class="card-header">{{ $lang === 'ar' ? 'تفاصيل الحجز والزيارات' : 'Booking Details' }}</div>
            <div class="card-body">
                <table class="details-table">
                    <tr>
                        <td>
                            <div class="label">{{ $lang === 'ar' ? 'تواريخ الزيارات' : 'Service Dates' }}</div>
                            <div class="value" style="font-weight: normal; font-size: 8.5pt;">
                                @foreach($dates as $date)
                                    • {{ $date }}<br/>
                                @endforeach
                            </div>
                        </td>
                        <td>
                            <div class="label">{{ $lang === 'ar' ? 'توقيت الوردية' : 'Start - End Time' }}</div>
                            <div class="value">{{ $shiftTime }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="label">{{ $lang === 'ar' ? 'مدة الزيارة' : 'Duration' }}</div>
                            <div class="value">{{ $durationHours }} {{ $lang === 'ar' ? 'ساعات' : 'Hours' }}</div>
                        </td>
                        <td>
                            <div class="label">{{ $lang === 'ar' ? 'عدد العمالة المطلوبة' : 'Number of Workers' }}</div>
                            <div class="value">{{ $workersCount }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="label">{{ $lang === 'ar' ? 'العنوان المحدد لتقديم الخدمة' : 'Service Address' }}</div>
                            <div class="value">{{ $customer['address'] }}</div>
                        </td>
                        <td>
                            <div class="label">{{ $lang === 'ar' ? 'تفاصيل الحضور والتأخير' : 'Arrival & Delay Details' }}</div>
                            <div class="value" style="font-weight: normal; font-size: 8.5pt;">
                                {{ $lang === 'ar' ? "فترة سماح الحضور: {$arrivalBuffer} دقيقة." : "Arrival grace period: {$arrivalBuffer} minutes." }}
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- 5. Allocated Worker Details -->
        @php
            $workerTitlesAr = [
                0 => 'تفاصيل العاملة الأولى',
                1 => 'تفاصيل العاملة الثانية',
                2 => 'تفاصيل العاملة الثالثة',
                3 => 'تفاصيل العاملة الرابعة',
                4 => 'تفاصيل العاملة الخامسة',
            ];
            $workerTitlesEn = [
                0 => 'First Worker Details',
                1 => 'Second Worker Details',
                2 => 'Third Worker Details',
                3 => 'Fourth Worker Details',
                4 => 'Fifth Worker Details',
            ];
        @endphp

        @if(!empty($allocatedWorkers))
            @foreach($allocatedWorkers as $idx => $worker)
                <div class="card">
                    <div class="card-header">
                        {{ $lang === 'ar' ? ($workerTitlesAr[$idx] ?? 'تفاصيل العاملة') : ($workerTitlesEn[$idx] ?? 'Worker Details') }}
                    </div>
                    <div class="card-body">
                        <table class="details-table">
                            <tr>
                                <td>
                                    <div class="label">{{ $lang === 'ar' ? 'الاسم' : 'Name' }}</div>
                                    <div class="value">{{ $worker['name'] }}</div>
                                </td>
                                <td>
                                    <div class="label">{{ $lang === 'ar' ? 'الجنسية' : 'Nationality' }}</div>
                                    <div class="value">{{ $worker['nationality'] }}</div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="label">{{ $lang === 'ar' ? 'الجنس' : 'Gender' }}</div>
                                    <div class="value">{{ $lang === 'ar' && strtolower($worker['gender']) === 'female' ? 'أنثى' : ($lang === 'ar' ? 'ذكر' : ucfirst($worker['gender'])) }}</div>
                                </td>
                                <td>
                                    <div class="label">{{ $lang === 'ar' ? 'رقم الهوية/الإقامة' : 'ID/Iqama Number' }}</div>
                                    <div class="value">{{ $worker['id_number'] ?? '—' }}</div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            @endforeach
        @endif

        <!-- 6. Payment Information Card -->
        <div class="card">
            <div class="card-header">{{ $lang === 'ar' ? 'معلومات الدفع وتفاصيل الأسعار' : 'Payment Information' }}</div>
            <div class="card-body">
                <table class="details-table">
                    <tr>
                        <td>
                            <div class="label">{{ $lang === 'ar' ? 'سعر الخدمة الإجمالي' : 'Service Price' }}</div>
                            <div class="value">{{ number_format($pricing['subtotal'], 2) }} {{ $currency }}</div>
                        </td>
                        <td>
                            <div class="label">{{ $lang === 'ar' ? 'الخصومات المطبقة' : 'Applied Discounts' }}</div>
                            <div class="value" style="color: #F45B8C;">
                                {{ $pricing['discount'] > 0 ? '-' : '' }}{{ number_format($pricing['discount'], 2) }} {{ $currency }}
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="label">{{ $lang === 'ar' ? 'ضريبة القيمة المضافة' : 'VAT Tax' }} ({{ $vatPercentage }}%)</div>
                            <div class="value">{{ number_format($pricing['tax'], 2) }} {{ $currency }}</div>
                        </td>
                        <td>
                            <div class="label">{{ $lang === 'ar' ? 'طريقة الدفع المحددة' : 'Payment Method' }}</div>
                            <div class="value">{{ $paymentMethod }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="border-top: 1px solid #eeeeee; padding-top: 10px; margin-top: 5px;">
                            <div class="label" style="font-size: 9pt; color: #F45B8C;">{{ $lang === 'ar' ? 'المبلغ الإجمالي المستحق' : 'Total Amount Due' }}</div>
                            <div class="value" style="font-size: 14pt; color: #F45B8C;">{{ number_format($pricing['total'], 2) }} {{ $currency }}</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- 7. Cancellation Policy Card -->
        <div class="card">
            <div class="card-header">{{ $lang === 'ar' ? 'سياسة الإلغاء' : 'Cancellation Policy' }}</div>
            <div class="card-body">
                <table class="policy-list">
                    <tr>
                        <td class="policy-desc">{{ $lang === 'ar' ? 'السماح بالإلغاء' : 'Cancellation Allowed' }}</td>
                        <td class="policy-val" style="color: {{ $rules['allow_cancel'] ? 'green' : 'red' }};">
                            {{ $rules['allow_cancel'] ? ($lang === 'ar' ? 'نعم' : 'Yes') : ($lang === 'ar' ? 'لا' : 'No') }}
                        </td>
                    </tr>
                    @if($rules['allow_cancel'])
                        <tr>
                            <td class="policy-desc">{{ $lang === 'ar' ? 'مهلة الإلغاء المجاني' : 'Free Cancellation Cutoff' }}</td>
                            <td class="policy-val">
                                {{ $rules['free_cancel_cutoff_hours'] }} {{ $lang === 'ar' ? 'ساعة قبل البدء' : 'Hours before start' }}
                            </td>
                        </tr>
                    @endif
                </table>
            </div>
        </div>

        <!-- 8. Reschedule Policy Card -->
        <div class="card">
            <div class="card-header">{{ $lang === 'ar' ? 'سياسة إعادة الجدولة' : 'Reschedule Policy' }}</div>
            <div class="card-body">
                <table class="policy-list">
                    <tr>
                        <td class="policy-desc">{{ $lang === 'ar' ? 'السماح بإعادة الجدولة' : 'Reschedule Allowed' }}</td>
                        <td class="policy-val" style="color: {{ $rules['allow_reschedule'] ? 'green' : 'red' }};">
                            {{ $rules['allow_reschedule'] ? ($lang === 'ar' ? 'نعم' : 'Yes') : ($lang === 'ar' ? 'لا' : 'No') }}
                        </td>
                    </tr>
                    @if($rules['allow_reschedule'])
                        <tr>
                            <td class="policy-desc">{{ $lang === 'ar' ? 'مهلة إعادة الجدولة' : 'Reschedule Cutoff' }}</td>
                            <td class="policy-val">
                                {{ $rules['reschedule_cutoff_hours'] }} {{ $lang === 'ar' ? 'ساعة قبل البدء' : 'Hours before start' }}
                            </td>
                        </tr>
                    @endif
                </table>
            </div>
        </div>

        <!-- 9. Terms and Conditions Card -->
        @if(!empty($terms))
            <div class="card">
                <div class="card-header">{{ $lang === 'ar' ? 'الشروط والأحكام الخاصة بالخدمة' : 'Terms & Conditions' }}</div>
                <div class="terms-box">
                    {!! nl2br(e($terms)) !!}
                </div>
            </div>
        @endif

    </div> <!-- /main-content-container -->

</body>

</html>
