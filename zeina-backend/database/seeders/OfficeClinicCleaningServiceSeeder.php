<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Service;
use App\Models\ServiceOption;
use App\Models\ServiceRule;
use App\Models\ServiceSchedule;
use App\Models\ServiceZone;
use App\Models\Team;
use App\Models\Worker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class OfficeClinicCleaningServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $category = Category::where('id', 6)->orWhere('name_en', 'like', '%Other%')->first();
        if (! $category) {
            $category = Category::firstOrCreate(
                ['name_en' => 'Other Services'],
                ['name_ar' => 'خدمات أخرى', 'status' => 'active']
            );
        }

        $service = Service::updateOrCreate(
            ['service_code' => 'OFFICE_CLINIC_CLEANING'],
            [
                'category_id' => $category->id,
                'type' => 'other',
                'pricing_type' => 'per_service',
                'name_ar' => 'تنظيف المكاتب الإدارية والعيادات',
                'name_en' => 'Administrative Offices & Clinics Cleaning',
                'description_ar' => 'خدمة متخصصة لتنظيف وتطهير وتعقيم المكاتب الإدارية والشركات والعيادات والمراكز الطبية بأحدث المعدات ومواد التعقيم المعتمدة، لضمان بيئة عمل وصحية راقية وآمنة.',
                'description_en' => 'Specialized professional deep cleaning and sanitization for administrative offices, corporate workspaces, medical clinics, and healthcare centers using approved hospital-grade disinfectants.',
                'features' => [
                    ['ar' => 'تعقيم وتطهير شامل لغرف الكشف والاستقبال ومكاتب العمل', 'en' => 'Comprehensive disinfection of consultation rooms, lobbies, and workspaces'],
                    ['ar' => 'تنظيف وتلميع الأرضيات والممرات والواجهات الزجاجية', 'en' => 'Deep floor scrubbing, polishing, and glass surface cleaning'],
                    ['ar' => 'تفريغ وتعقيم سلات المهملات وتطهير نقاط التلامس المشتركة', 'en' => 'Sanitizing high-touch touchpoints and clinical waste management'],
                    ['ar' => 'استخدام منظفات ومعقمات معتمدة طبياً وآمنة للأجهزة الحساسة', 'en' => 'Medical-grade, eco-friendly sanitizers safe for sensitive equipment'],
                ],
                'status' => 'active',
                'is_team_based' => true,
                'worker_gender_options' => ['Male'],
                'default_duration' => 4,
                'min_duration' => 4,
                'max_duration' => 4,
                'has_duration_range' => false,
                'min_workers' => 3,
                'max_workers' => 4,
                'base_price' => 0.00,
                'available_visit_types' => [['type' => 'single_visit', 'discount' => 0]],
                'start_time' => '08:00',
                'end_time' => '18:00',
                'terms_ar' => "1. السعر شامل الخدمة كاملة والعمالة المخصصة.\n2. مدة الخدمة 4 ساعات للشفت.\n3. يرجى تأمين الأوراق والمستندات الهامة داخل الأدراج أو الخزائن قبل بدء الخدمة.\n4. يشمل الحجز تنظيف وتطهير المساحات المحددة وفق الخيار المختار.",
                'terms_en' => "1. The flat price covers the complete service and assigned crew.\n2. Service shift duration is 4 hours.\n3. Please secure confidential files and valuables in locked cabinets prior to cleaning.\n4. The booking encompasses deep cleaning and sanitization according to the selected option.",
            ]
        );

        // 1. Service Rules
        ServiceRule::updateOrCreate(
            ['service_id' => $service->id],
            [
                'allow_cancel' => true,
                'free_cancel_cutoff_hours' => 24,
                'allow_reschedule' => true,
                'reschedule_cutoff_hours' => 24,
                'allow_same_day' => true,
                'same_day_lead_time_hours' => 3,
                'same_day_cutoff_time' => '10:00:00',
                'max_advance_booking_days' => 30,
                'arrival_buffer_minutes' => 15,
                'late_arrival_grace_minutes' => 15,
            ]
        );

        // 2. Service Options (Two Options per Document V.19 - Price only, no min/max qty)
        $optionsData = [
            [
                'name_ar' => 'الصغيرة والمتوسطة (60–120 م²)',
                'name_en' => 'Small & Medium (60–120 m²)',
                'description_ar' => 'المكاتب والعيادات الصغيرة والمتوسطة (60–120 م²) - تشمل 3 عمال لمدة 4 ساعات',
                'description_en' => 'Small to medium offices & clinics (60–120 m²) - includes 3 workers for 4 hours',
                'unit_price' => 200.00,
                'min_qty' => null,
                'max_qty' => null,
                'status' => 'active',
                'sort_order' => 1,
            ],
            [
                'name_ar' => 'الكبيرة (أكثر من 300 م²)',
                'name_en' => 'Large (> 300 m²)',
                'description_ar' => 'المكاتب والمراكز والعيادات الكبيرة (أكثر من 300 م²) - تشمل 4 عمال لمدة 4 ساعات',
                'description_en' => 'Large corporate offices & clinics (> 300 m²) - includes 4 workers for 4 hours',
                'unit_price' => 500.00,
                'min_qty' => null,
                'max_qty' => null,
                'status' => 'active',
                'sort_order' => 2,
            ],
        ];

        foreach ($optionsData as $opt) {
            ServiceOption::updateOrCreate(
                [
                    'service_id' => $service->id,
                    'name_ar' => $opt['name_ar'],
                ],
                array_merge($opt, ['service_id' => $service->id])
            );
        }

        // 3. Service Schedules (Shifts 08:00 - 12:00 and 14:00 - 18:00)
        $days = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        $shifts = [
            ['start_time' => '08:00:00', 'end_time' => '12:00:00'],
            ['start_time' => '14:00:00', 'end_time' => '18:00:00'],
        ];

        foreach ($days as $day) {
            foreach ($shifts as $shift) {
                ServiceSchedule::updateOrCreate(
                    [
                        'service_id' => $service->id,
                        'day' => $day,
                        'start_time' => $shift['start_time'],
                    ],
                    [
                        'end_time' => $shift['end_time'],
                        'is_active' => true,
                    ]
                );
            }
        }

        // 4. Attach all active Service Zones
        $zoneIds = ServiceZone::where('status', 'active')->pluck('id')->toArray();
        if (! empty($zoneIds)) {
            $service->serviceZones()->syncWithoutDetaching($zoneIds);
        }

        // 5. Attach Male Teams & Male Workers (same as services 6 to 11)
        $maleTeamIds = Team::where('status', 'active')->pluck('id')->toArray();
        if (! empty($maleTeamIds)) {
            $service->teams()->syncWithoutDetaching($maleTeamIds);
        }

        $maleWorkerIds = Worker::where('gender', 'male')->where('status', 'active')->pluck('id')->toArray();
        if (! empty($maleWorkerIds)) {
            $service->workers()->syncWithoutDetaching($maleWorkerIds);
        }

        // 6. Sync Media Icon (12.jpg)
        $dummyIconPath = public_path('assist/services/12.jpg');
        if (file_exists($dummyIconPath)) {
            $extension = pathinfo($dummyIconPath, PATHINFO_EXTENSION);
            $iconName = 'service_' . $service->id . '_icon.' . $extension;
            $iconPath = 'services/icons/' . $iconName;

            Storage::disk('public')->put($iconPath, file_get_contents($dummyIconPath));

            $service->media()->updateOrCreate(
                ['collection' => 'service/icon'],
                [
                    'file_name' => $iconName,
                    'mime_type' => mime_content_type($dummyIconPath),
                    'disk' => 'public',
                    'path' => $iconPath,
                    'size' => Storage::disk('public')->size($iconPath),
                ]
            );
        }
    }
}
