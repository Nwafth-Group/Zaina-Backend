<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Service;
use App\Models\ServiceZone;
use App\Models\User;
use App\Service\ServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class CategoryAndServiceSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();

        // Fetch all Geo-Fenced Service Zones
        $allZoneIds = ServiceZone::pluck('id')->toArray();

        // قائمة الصور العشوائية للخدمات
        $dummyIconsList = ['icon_1.jpg', 'icon_2.jpg', 'icon_3.jpg', 'cover_1.jpg', 'cover_2.jpg', 'cover_3.jpeg'];

        // 1. تعريف الأقسام الرئيسية (Categories)
        $categoriesData = [
            'cleaning' => ['en' => 'Cleaning Services', 'ar' => 'خدمات التنظيف', 'icon' => 'cleanCategory.webp'],
            'babysitting' => ['en' => 'Nanny & Babysitting', 'ar' => 'رعاية الأطفال', 'icon' => 'Babysitting.webp'],
            'hospitality' => ['en' => 'Hospitality Services', 'ar' => 'خدمات الضيافة', 'icon' => 'Babysitting.webp'],
            'beauty' => ['en' => 'Beauty & Care', 'ar' => 'التجميل والعناية', 'icon' => 'Beauty&Spa.avif'],
            'maintenance' => ['en' => 'Home Maintenance', 'ar' => 'الصيانة المنزلية', 'icon' => 'Maintenance.avif'],
            'other' => ['en' => 'Other Services', 'ar' => 'خدمات أخرى', 'icon' => 'Beauty&Spa.avif'],
        ];

        $categories = [];
        foreach ($categoriesData as $key => $data) {
            $category = Category::firstOrCreate(
                ['name_en' => $data['en'], 'parent_id' => null],
                ['name_ar' => $data['ar'], 'status' => 'active', 'created_by' => $admin->id ?? null]
            );
            $categories[$key] = $category;

            // رفع الميديا للقسم
            $dummyIconPath = public_path('dummy/' . $data['icon']);
            if (file_exists($dummyIconPath)) {
                $extension = pathinfo($dummyIconPath, PATHINFO_EXTENSION);
                $iconName = 'category_' . $category->id . '_icon.' . $extension;
                $iconPath = 'categories/icons/' . $iconName;

                Storage::disk('public')->put($iconPath, file_get_contents($dummyIconPath));

                $category->media()->updateOrCreate(
                    ['collection' => 'category/icon'],
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

        // 2. تعريف الخدمات المحددة (11 خدمة)
        $servicesData = [
            // خدمات بالساعة (3)
            ['cat' => 'cleaning', 'type' => 'hourly', 'en' => 'Domestic Helper (Hourly)', 'ar' => 'عاملة منزلية بالساعة', 'price' => 80, 'period' => 2, 'code' => 'DOMESTIC_HELPER_HOURLY', 'img_num' => 1],
            ['cat' => 'hospitality', 'type' => 'hourly', 'en' => 'Hospitality Worker (Hourly)', 'ar' => 'عاملة ضيافة بالساعة', 'price' => 90, 'period' => 2, 'code' => 'HOSPITALITY_WORKER_HOURLY', 'img_num' => 2],
            ['cat' => 'babysitting', 'type' => 'hourly', 'en' => 'Babysitter (Hourly)', 'ar' => 'جليسة اطفال بالساعة', 'price' => 100, 'period' => 3, 'code' => 'BABYSITTER_HOURLY', 'img_num' => 3],

            // خدمات أخرى سابقة (2)
            ['cat' => 'other', 'type' => 'other', 'en' => 'Pedicure & Manicure Service', 'ar' => 'خدمة العناية بالأقدام واليدين', 'price' => 150, 'period' => 2, 'code' => 'PEDICURE_MANICURE', 'covered_persons_per_worker' => 1, 'img_num' => 4],
            ['cat' => 'other', 'type' => 'other', 'en' => 'Deep Cleaning Service', 'ar' => 'خدمة النظافة العميقة', 'price' => 500, 'period' => 6, 'code' => 'DEEP_CLEANING', 'img_num' => 5],

            // خدمات أخرى جديدة (6 خدمات)
            [
                'cat' => 'other',
                'type' => 'other',
                'en' => 'Sofa Cleaning Service',
                'ar' => 'تنظيف الكنب',
                'price' => 0,
                'period' => 2,
                'code' => 'SOFA_CLEANING',
                'img_num' => 6,
                'options' => [
                    ['name_ar' => 'كنب (شخص واحد)', 'name_en' => 'One-seat Sofa', 'unit_price' => 50, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 1],
                    ['name_ar' => 'كنب (2 أشخاص)', 'name_en' => 'Two-seat Sofa', 'unit_price' => 95, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 2],
                    ['name_ar' => 'كنب (3 أشخاص)', 'name_en' => 'Three-seat Sofa', 'unit_price' => 140, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 3],
                ],
            ],
            [
                'cat' => 'other',
                'type' => 'other',
                'en' => 'Water Tank Cleaning Service',
                'ar' => 'تنظيف خزانات المياه',
                'price' => 0,
                'period' => 2,
                'code' => 'WATER_TANK_CLEANING',
                'img_num' => 7,
                'options' => [
                    ['name_ar' => 'خزان علوي وسط', 'name_en' => 'Medium Overhead Tank', 'unit_price' => 1000, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 1],
                    ['name_ar' => 'خزان علوي كبير', 'name_en' => 'Large Overhead Tank', 'unit_price' => 1300, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 2],
                ],
            ],
            [
                'cat' => 'other',
                'type' => 'other',
                'en' => 'Cleaning the Stairs & Surfaces',
                'ar' => 'تنظيف السلالم والأسطح',
                'price' => 0,
                'period' => 2,
                'code' => 'STAIRS_SURFACES_CLEANING',
                'img_num' => 8,
                'options' => [
                    ['name_ar' => 'درج أرضي + 3 أدوار', 'name_en' => 'Ground + 3 Floors Stairs', 'unit_price' => 300, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 1],
                    ['name_ar' => 'درج أرضي + 4 أدوار', 'name_en' => 'Ground + 4 Floors Stairs', 'unit_price' => 330, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 2],
                    ['name_ar' => 'درج أرضي + 5 أدوار', 'name_en' => 'Ground + 5 Floors Stairs', 'unit_price' => 400, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 3],
                    ['name_ar' => 'سطح صغير حتى 150 متر', 'name_en' => 'Small Roof (up to 150m²)', 'unit_price' => 500, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 4],
                    ['name_ar' => 'سطح وسط حتى 270 متر', 'name_en' => 'Medium Roof (up to 270m²)', 'unit_price' => 700, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 5],
                    ['name_ar' => 'سطح كبير حتى 400 متر', 'name_en' => 'Large Roof (up to 400m²)', 'unit_price' => 1200, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 6],
                ],
            ],
            [
                'cat' => 'other',
                'type' => 'other',
                'en' => 'Air Conditioner Cleaning Service',
                'ar' => 'تنظيف المكيفات',
                'price' => 0,
                'period' => 2,
                'code' => 'AC_CLEANING',
                'img_num' => 9,
                'options' => [
                    ['name_ar' => 'مكيف واحد شباك', 'name_en' => '1 Window AC Unit', 'unit_price' => 300, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 1],
                    ['name_ar' => 'مكيف واحد سبليت', 'name_en' => '1 Split AC Unit', 'unit_price' => 500, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 2],
                ],
            ],
            [
                'cat' => 'other',
                'type' => 'other',
                'en' => 'Carpet & Rug Cleaning Service',
                'ar' => 'غسيل السجاد والموكيت',
                'price' => 0,
                'period' => 2,
                'code' => 'CARPET_CLEANING',
                'img_num' => 10,
                'options' => [
                    ['name_ar' => 'سجادة أو موكيت (3*3م)', 'name_en' => 'Carpet / Rug (3x3m)', 'unit_price' => 300, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 1],
                    ['name_ar' => 'سجادة أو موكيت (3*4م)', 'name_en' => 'Carpet / Rug (3x4m)', 'unit_price' => 300, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 2],
                    ['name_ar' => 'سجادة أو موكيت (5*5م)', 'name_en' => 'Carpet / Rug (5x5m)', 'unit_price' => 300, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 3],
                    ['name_ar' => 'سجادة أو موكيت (2*2م)', 'name_en' => 'Carpet / Rug (2x2m)', 'unit_price' => 300, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 4],
                    ['name_ar' => 'سجادة أو موكيت (2*3م)', 'name_en' => 'Carpet / Rug (2x3m)', 'unit_price' => 300, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 5],
                    ['name_ar' => 'سجادة أو موكيت (2*4م)', 'name_en' => 'Carpet / Rug (2x4m)', 'unit_price' => 300, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 6],
                    ['name_ar' => 'سجادة أو موكيت (5*2م)', 'name_en' => 'Carpet / Rug (5x2m)', 'unit_price' => 300, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 7],
                ],
            ],
            [
                'cat' => 'other',
                'type' => 'other',
                'en' => 'Car Wash Service',
                'ar' => 'غسيل السيارات',
                'price' => 0,
                'period' => 2,
                'code' => 'CAR_WASH',
                'img_num' => 11,
                'options' => [
                    ['name_ar' => 'سيارة صغيرة خارجي وداخلي (شامل الكراسي والسقف)', 'name_en' => 'Small Car (Interior & Exterior)', 'unit_price' => 500, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 1],
                    ['name_ar' => 'سيارة وسط خارجي وداخلي (شامل الكراسي والسقف)', 'name_en' => 'Medium Car (Interior & Exterior)', 'unit_price' => 700, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 2],
                    ['name_ar' => 'سيارة كبيرة خارجي وداخلي (شامل الكراسي والسقف)', 'name_en' => 'Large Car (Interior & Exterior)', 'unit_price' => 1000, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 3],
                ],
            ],
        ];

        // 3. إدخال الخدمات وتركيب القواعد والأسعار والمناطق
        foreach ($servicesData as $index => $data) {
            $features = [
                ['en' => 'Dusting All Surfaces', 'ar' => 'مسح جميع الأسطح'],
                ['en' => 'Floor Cleaning', 'ar' => 'تنظيف الأرضيات'],
                ['en' => 'High-quality equipment', 'ar' => 'معدات عالية الجودة'],
            ];

            $isFirstService = ($data['code'] === 'DOMESTIC_HELPER_HOURLY');
            $isSecondService = ($data['code'] === 'HOSPITALITY_WORKER_HOURLY');
            $isThirdService = ($data['code'] === 'BABYSITTER_HOURLY');
            $isPedicureManicure = ($data['code'] === 'PEDICURE_MANICURE');
            $isDeepCleaning = ($data['code'] === 'DEEP_CLEANING');
            $isOtherNewService = in_array($data['code'], ['SOFA_CLEANING', 'WATER_TANK_CLEANING', 'STAIRS_SURFACES_CLEANING', 'AC_CLEANING', 'CARPET_CLEANING', 'CAR_WASH']);

            // Construct payload matching StoreServiceRequest structure exactly
            $payload = [
                'category_id' => $categories[$data['cat']]->id,
                'service_code' => $data['code'],
                'type' => $data['type'],
                'name_ar' => $data['ar'],
                'name_en' => $data['en'],
                'description_ar' => 'وصف تفصيلي لخدمة ' . $data['ar'],
                'description_en' => 'Detailed description for ' . $data['en'],
                'features' => $features,
                'status' => 'active',

                'available_visit_types' => ($isSecondService || $isThirdService || $isPedicureManicure || $isDeepCleaning || $isOtherNewService)
                    ? [['type' => 'single_visit', 'discount' => 0]]
                    : [
                        ['type' => 'single_visit', 'discount' => 0],
                        ['type' => '3_days_per_week', 'discount' => 15],
                    ],
                'base_price' => $data['price'],
                'start_time' => '08:00',
                'end_time' => '20:00',
                'default_duration' => ($isSecondService || $isThirdService) ? 4 : $data['period'],
                'has_duration_range' => ($isSecondService || $isThirdService),
                'min_duration' => ($isSecondService || $isThirdService) ? 4 : null,
                'max_duration' => ($isSecondService || $isThirdService) ? 6 : null,
                'min_workers' => $isDeepCleaning ? 6 : ($isOtherNewService ? 3 : 1),
                'max_workers' => $isDeepCleaning ? 6 : ($isOtherNewService ? 3 : 6),
                'min_children' => $isThirdService ? 1 : null,
                'max_children' => $isThirdService ? 6 : null,
                'covered_persons_per_worker' => $data['covered_persons_per_worker'] ?? null,

                'service_zone_ids' => $allZoneIds,

                'working_days' => ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'saturday'],
                'shifts' => ($isSecondService || $isThirdService || $isPedicureManicure) ? [] : [
                    ['start_time' => '08:00'],
                    ['start_time' => '14:00'],
                ],

                'terms_ar' => "1. يجب توفير بيئة عمل آمنة ومناسبة للعمالة.\n2. لا يحق للعميل تكليف العمال بأي أعمال خارج نطاق الخدمة المتفق عليها.\n3. يجب إبلاغ الشركة بأي تغييرات في موعد الخدمة قبل 24 ساعة على الأقل.\n4. الشركة غير مسؤولة عن فقدان المقتنيات الثمينة غير المصرح عنها.",
                'terms_en' => "1. A safe and suitable working environment must be provided for the worker.\n2. The client is not entitled to assign any tasks to the worker outside the scope of the agreed service.\n3. The company must be notified of any changes to the service schedule at least 24 hours in advance.\n4. The company is not responsible for the loss of undeclared valuable items.",

                'rules' => [
                    'allow_cancel' => true,
                    'free_cancel_cutoff_hours' => 24,
                    'allow_reschedule' => true,
                    'reschedule_cutoff_hours' => 24,
                    'allow_same_day' => false,
                    'max_advance_booking_days' => 30,
                    'arrival_buffer_minutes' => 15,
                    'late_arrival_grace_minutes' => 15,
                ],
                'points_redemption_enabled' => true,
                'points_redemption_method' => 'price_based',
                'points_required_fixed' => 1000,
                'points_conversion_rate' => 10,
            ];

            if (isset($data['options'])) {
                $payload['options'] = $data['options'];
            }

            if ($isFirstService || $isThirdService) {
                $payload['worker_gender_options'] = ['Female'];
                $payload['pricing_type'] = 'per_hour';
            } elseif ($isSecondService) {
                $payload['worker_gender_options'] = ['Female', 'Male'];
                $payload['pricing_type'] = 'per_hour';
            } elseif ($isPedicureManicure) {
                $payload['worker_gender_options'] = ['Female'];
                $payload['pricing_type'] = 'per_service';
            } else {
                $payload['worker_gender_options'] = ['Male'];
                $payload['pricing_type'] = 'per_service';
            }

            if ($isThirdService) {
                $payload['child_age_groups'] = [
                    [
                        'name_ar' => 'حديثي الولادة إلى سنتين',
                        'name_en' => 'Newborn to 2 Years',
                        'age_from' => 0,
                        'age_to' => 24,
                        'calculation_type' => 'ratio_based',
                        'children_count' => 1,
                        'required_worker_count' => 1,
                        'status' => 'active',
                        'note' => '1 babysitter for each child (مربية واحدة لكل طفل)',
                    ],
                    [
                        'name_ar' => 'من 2 إلى 7 سنوات',
                        'name_en' => '2 to 7 Years',
                        'age_from' => 25,
                        'age_to' => 84,
                        'calculation_type' => 'ratio_based',
                        'children_count' => 2,
                        'required_worker_count' => 1,
                        'status' => 'active',
                        'note' => '1 babysitter for every 2 children (1 جليسة لكل 2 أطفال)',
                    ]
                ];
            }

            // Write service and sync its relations using the ServiceWriter class
            $service = Service::where('name_en', $data['en'])->first();
            if ($service) {
                $service = app(ServiceWriter::class)->update($service, $payload);
            } else {
                $service = app(ServiceWriter::class)->create($payload);
            }

            // Sync media icon
            $imgNum = $data['img_num'] ?? ($index + 1);
            $dummyIconPath = public_path("assist/services/{$imgNum}.png");
            if (!file_exists($dummyIconPath)) {
                $dummyIconPath = public_path("assist/services/{$imgNum}.webp");
            }
            if (!file_exists($dummyIconPath)) {
                $selectedIcon = Arr::random($dummyIconsList);
                $dummyIconPath = public_path('dummy/' . $selectedIcon);
            }

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
        $this->command->info('Categories and Services seeded successfully with comprehensive fallback city prices and custom area scopes!');
    }
}
