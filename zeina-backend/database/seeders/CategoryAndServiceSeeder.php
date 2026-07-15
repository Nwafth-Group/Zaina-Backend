<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Category;
use App\Models\City;
use App\Models\Service;
use App\Models\User;
use App\Models\Worker;
use App\Service\ServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class CategoryAndServiceSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();

        // 🌟 جلب جميع المدن والمناطق المتوفرة لبناء سيناريو توزيع جغرافي حقيقي ومضمون
        $cities = City::all();
        $areas = Area::all();

        if ($cities->isEmpty() || $areas->isEmpty()) {
            $this->command->error('must have cities and areas in the database before running this seeder to ensure proper geographical distribution of services.');

            return;
        }

        // قائمة الصور العشوائية للخدمات
        $dummyIconsList = ['icon_1.jpg', 'icon_2.jpg', 'icon_3.jpg', 'cover_1.jpg', 'cover_2.jpg', 'cover_3.jpeg'];

        // 1. تعريف الأقسام الرئيسية (Categories)
        $categoriesData = [
            'cleaning' => ['en' => 'Cleaning Services', 'ar' => 'خدمات التنظيف', 'icon' => 'cleanCategory.webp'],
            'babysitting' => ['en' => 'Nanny & Babysitting', 'ar' => 'رعاية الأطفال', 'icon' => 'Babysitting.webp'],
            'hospitality' => ['en' => 'Hospitality Services', 'ar' => 'خدمات الضيافة', 'icon' => 'Babysitting.webp'],
            'beauty' => ['en' => 'Beauty & Care', 'ar' => 'التجميل والعناية', 'icon' => 'Beauty&Spa.avif'],
            'maintenance' => ['en' => 'Home Maintenance', 'ar' => 'الصيانة المنزلية', 'icon' => 'Maintenance.avif'],
        ];

        $categories = [];
        foreach ($categoriesData as $key => $data) {
            $category = Category::firstOrCreate(
                ['name_en' => $data['en'], 'parent_id' => null],
                ['name_ar' => $data['ar'], 'status' => 'active', 'created_by' => $admin->id ?? null]
            );
            $categories[$key] = $category;

            // رفع الميديا للقسم
            $dummyIconPath = public_path('dummy/'.$data['icon']);
            if (file_exists($dummyIconPath)) {
                $extension = pathinfo($dummyIconPath, PATHINFO_EXTENSION);
                $iconName = 'category_'.$category->id.'_icon.'.$extension;
                $iconPath = 'categories/icons/'.$iconName;

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

        // 2. تعريف الخدمات الجديدة المحددة بالصورة (9 خدمات)
        $servicesData = [
            // خدمات بالساعة (3)
            ['cat' => 'cleaning', 'type' => 'hourly', 'en' => 'Domestic Helper (Hourly)', 'ar' => 'عاملة منزلية بالساعة', 'price' => 80, 'period' => 2, 'code' => 'DOMESTIC_HELPER_HOURLY'],
            ['cat' => 'hospitality', 'type' => 'hourly', 'en' => 'Hospitality Worker (Hourly)', 'ar' => 'عاملة ضيافة بالساعة', 'price' => 90, 'period' => 2, 'code' => 'HOSPITALITY_WORKER_HOURLY'],
            ['cat' => 'babysitting', 'type' => 'hourly', 'en' => 'Babysitter (Hourly)', 'ar' => 'جليسة اطفال بالساعة', 'price' => 100, 'period' => 3, 'code' => 'BABYSITTER_HOURLY'],

            // خدمات أخرى (6)
            // ['cat' => 'beauty',      'type' => 'other',  'en' => 'Manicure & Pedicure', 'ar' => 'مناكير و بدكير', 'price' => 150, 'period' => 1, 'code' => 'MANICURE_PEDICURE'],
            // ['cat' => 'cleaning',    'type' => 'other',  'en' => 'Sofa Cleaning', 'ar' => 'تنظيف الكنب', 'price' => 130, 'period' => 2, 'code' => 'SOFA_CLEANING'],
            // ['cat' => 'maintenance', 'type' => 'other',  'en' => 'AC Cleaning', 'ar' => 'تنظيف المكيفات', 'price' => 200, 'period' => 2, 'code' => 'AC_CLEANING'],
            // ['cat' => 'maintenance', 'type' => 'other',  'en' => 'Pest Control', 'ar' => 'مكافحة الحشرات', 'price' => 180, 'period' => 2, 'code' => 'PEST_CONTROL'],
            // ['cat' => 'cleaning',    'type' => 'other',  'en' => 'Cleaning of Stairs, Roofs & Yards', 'ar' => 'تنظيف السلالم والاسطح والاحواش', 'price' => 250, 'period' => 3, 'code' => 'OUTDOOR_CLEANING'],
            // ['cat' => 'cleaning',    'type' => 'other',  'en' => 'Water Tank Cleaning', 'ar' => 'تنظيف خزانات المياه', 'price' => 300, 'period' => 4, 'code' => 'WATER_TANK_CLEANING'],
        ];

        // 3. إدخال الخدمات وتركيب القواعد والأسعار والمناطق
        foreach ($servicesData as $index => $data) {
            $features = [
                ['en' => 'Dusting All Surfaces', 'ar' => 'مسح جميع الأسطح'],
                ['en' => 'Floor Cleaning', 'ar' => 'تنظيف الأرضيات'],
                ['en' => 'High-quality equipment', 'ar' => 'معدات عالية الجودة'],
            ];

            // Build coverages matching the request body format
            $coverages = [];
            foreach ($cities as $city) {
                $coverages[] = [
                    'city_id' => $city->id,
                    'area_id' => null,
                    'status' => 'active',
                ];
            }

            if ($index % 2 !== 0) {
                $randomAreas = $areas->random(min(3, $areas->count()));
                foreach ($randomAreas as $selectedArea) {
                    $coverages[] = [
                        'city_id' => $selectedArea->city_id,
                        'area_id' => $selectedArea->id,
                        'status' => 'active',
                    ];
                }
            }

            $isFirstService = ($data['code'] === 'DOMESTIC_HELPER_HOURLY');
            $isSecondService = ($data['code'] === 'HOSPITALITY_WORKER_HOURLY');
            $isThirdService = ($data['code'] === 'BABYSITTER_HOURLY');

            $genderAttr = ($isFirstService || $isThirdService) ? 'female' : fake()->randomElement(['male', 'female']);

            // Sync workers to this service
            $newWorkers = Worker::factory(3)->create([
                'status' => 'active',
                'gender' => $genderAttr,
            ]);
            $workerIds = $newWorkers->pluck('id')->toArray();

            // Construct payload matching StoreServiceRequest structure exactly
            $payload = [
                'category_id' => $categories[$data['cat']]->id,
                'service_code' => $data['code'],
                'type' => $data['type'],
                'name_ar' => $data['ar'],
                'name_en' => $data['en'],
                'description_ar' => 'وصف تفصيلي لخدمة '.$data['ar'],
                'description_en' => 'Detailed description for '.$data['en'],
                'features' => $features,
                'status' => 'active',
                'workers' => $workerIds,

                'available_visit_types' => ($isSecondService || $isThirdService)
                    ? [['type' => 'single_visit', 'discount' => 0]]
                    : [
                        ['type' => 'single_visit', 'discount' => 0],
                        ['type' => '3_days_per_week', 'discount' => 15],
                    ],
                'base_price' => $data['price'],
                'start_time' => '08:00',
                'end_time' => ($isSecondService || $isThirdService) ? '20:00' : '22:00',
                'duration_unit' => 'Hours',
                'default_duration' => ($isSecondService || $isThirdService) ? 4 : $data['period'],
                'has_duration_range' => ($isSecondService || $isThirdService),
                'min_duration' => ($isSecondService || $isThirdService) ? 4 : null,
                'max_duration' => ($isSecondService || $isThirdService) ? 6 : null,
                'min_workers' => 1,
                'max_workers' => 6,
                'min_children' => $isThirdService ? 1 : null,
                'max_children' => $isThirdService ? 6 : null,

                'coverages' => $coverages,

                'working_days' => ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'saturday'],
                'shifts' => ($isSecondService || $isThirdService) ? [] : [
                    ['start_time' => '09:00'],
                    ['start_time' => '12:00'],
                    ['start_time' => '14:00'],
                    ['start_time' => '16:00'],
                ],

                'terms_ar' => "1. يجب توفير بيئة عمل آمنة ومناسبة للعاملة.\n2. لا يحق للعميل تكليف العاملة بأي أعمال خارج نطاق الخدمة المتفق عليها.\n3. يجب إبلاغ الشركة بأي تغييرات في موعد الخدمة قبل 24 ساعة على الأقل.\n4. الشركة غير مسؤولة عن فقدان المقتنيات الثمينة غير المصرح عنها.",
                'terms_en' => "1. A safe and suitable working environment must be provided for the worker.\n2. The client is not entitled to assign any tasks to the worker outside the scope of the agreed service.\n3. The company must be notified of any changes to the service schedule at least 24 hours in advance.\n4. The company is not responsible for the loss of undeclared valuable items.",
                'acceptance_required' => true,

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
            ];

            if ($isFirstService || $isThirdService) {
                $payload['worker_gender_options'] = ['Female'];
                $payload['pricing_type'] = 'per_hour';
            } elseif ($isSecondService) {
                $payload['worker_gender_options'] = ['Female', 'Male'];
                $payload['pricing_type'] = 'per_hour';
            } else {
                $payload['worker_gender_options'] = [];
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
                    ],
                    [
                        'name_ar' => 'أكبر من 3 سنوات',
                        'name_en' => 'Over 3 Years',
                        'age_from' => 36,
                        'age_to' => 1200,
                        'calculation_type' => 'ratio_based',
                        'children_count' => 3,
                        'required_worker_count' => 2,
                        'status' => 'active',
                        'note' => '2 babysitters for every 3 children (2 جليسة لكل 3 أطفال)',
                    ],
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
            $dummyIconPath = public_path("assist/services/{$service->id}.webp");
            if (! file_exists($dummyIconPath)) {
                $selectedIcon = Arr::random($dummyIconsList);
                $dummyIconPath = public_path('dummy/'.$selectedIcon);
            }

            if (file_exists($dummyIconPath)) {
                $extension = pathinfo($dummyIconPath, PATHINFO_EXTENSION);
                $iconName = 'service_'.$service->id.'_icon.'.$extension;
                $iconPath = 'services/icons/'.$iconName;

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
