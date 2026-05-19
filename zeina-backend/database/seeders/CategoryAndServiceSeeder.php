<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Service;
use App\Models\City;
use App\Models\Area;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr; 

class CategoryAndServiceSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first(); 
        
        // 🌟 جلب جميع المدن والمناطق المتوفرة لبناء سيناريو توزيع جغرافي حقيقي
        $cities = City::all();
        $areas = Area::all();

        if ($cities->isEmpty() || $areas->isEmpty()) {
            $this->command->error('must have cities and areas in the database before running this seeder to ensure proper geographical distribution of services.');
            return;
        }

        // قائمة الصور العشوائية للأقسام الفرعية والخدمات
        $dummyIconsList = ['icon_1.jpg', 'icon_2.jpg', 'icon_3.jpg', 'cover_1.jpg', 'cover_2.jpg', 'cover_3.jpeg'];

        // 1. تعريف الأقسام الرئيسية (Main Categories)
        $mainCategoriesData = [
            'cleaning'    => ['en' => 'Cleaning', 'ar' => 'تنظيف', 'icon' => 'cleanCategory.webp'],
            'babysitting' => ['en' => 'Babysitting', 'ar' => 'رعاية أطفال', 'icon' => 'Babysitting.webp'],
            'maintenance' => ['en' => 'Maintenance', 'ar' => 'صيانة منزلية', 'icon' => 'Maintenance.avif'],
            'beauty'      => ['en' => 'Beauty & Spa', 'ar' => 'تجميل وعناية', 'icon' => 'Beauty&Spa.avif'],
        ];

        $mainCategories = [];
        foreach ($mainCategoriesData as $key => $data) {
            $category = Category::firstOrCreate(
                ['name_en' => $data['en'], 'parent_id' => null],
                ['name_ar' => $data['ar'], 'status' => 'active', 'created_by' => $admin->id ?? null]
            );
            $mainCategories[$key] = $category;

            // رفع الميديا للقسم الرئيسي
            $dummyIconPath = public_path('dummy/' . $data['icon']);
            if (file_exists($dummyIconPath)) {
                $extension = pathinfo($dummyIconPath, PATHINFO_EXTENSION);
                $iconName = 'category_main_' . $category->id . '_icon.' . $extension;
                $iconPath = 'categories/icons/' . $iconName; 
                
                Storage::disk('public')->put($iconPath, file_get_contents($dummyIconPath));
                
                $category->media()->updateOrCreate(
                    ['collection' => 'category/icon'],
                    [
                        'file_name' => $iconName,
                        'mime_type' => mime_content_type($dummyIconPath),
                        'disk'      => 'public',
                        'path'      => $iconPath,
                        'size'      => Storage::disk('public')->size($iconPath),
                    ]
                );
            }
        }

        // 2. تعريف الأقسام الفرعية (Subcategories) 
        $subCategoriesData = [
            'home_cleaning'   => ['en' => 'Home Cleaning', 'ar' => 'تنظيف منازل', 'parent' => 'cleaning'],
            'carpet_cleaning' => ['en' => 'Carpet & Furniture', 'ar' => 'سجاد ومفروشات', 'parent' => 'cleaning'],
            'child_care'      => ['en' => 'Child Care', 'ar' => 'جليسة أطفال', 'parent' => 'babysitting'],
            'plumbing'        => ['en' => 'Plumbing', 'ar' => 'سباكة', 'parent' => 'maintenance'],
            'electrical'      => ['en' => 'Electrical', 'ar' => 'كهرباء', 'parent' => 'maintenance'],
            'hair_makeup'     => ['en' => 'Hair & Makeup', 'ar' => 'شعر ومكياج', 'parent' => 'beauty'],
        ];

        $subCategories = [];
        foreach ($subCategoriesData as $key => $data) {
            $subCategory = Category::firstOrCreate(
                ['name_en' => $data['en'], 'parent_id' => $mainCategories[$data['parent']]->id],
                ['name_ar' => $data['ar'], 'status' => 'active', 'created_by' => $admin->id ?? null]
            );
            $subCategories[$key] = $subCategory;

            // رفع الميديا للقسم الفرعي
            $selectedIcon = Arr::random($dummyIconsList);
            $dummyIconPath = public_path('dummy/' . $selectedIcon);
            if (file_exists($dummyIconPath)) {
                $extension = pathinfo($dummyIconPath, PATHINFO_EXTENSION);
                $iconName = 'category_sub_' . $subCategory->id . '_icon.' . $extension;
                $iconPath = 'categories/icons/' . $iconName;
                
                Storage::disk('public')->put($iconPath, file_get_contents($dummyIconPath));
                
                $subCategory->media()->updateOrCreate(
                    ['collection' => 'category/icon'],
                    [
                        'file_name' => $iconName,
                        'mime_type' => mime_content_type($dummyIconPath),
                        'disk'      => 'public',
                        'path'      => $iconPath,
                        'size'      => Storage::disk('public')->size($iconPath),
                    ]
                );
            }
        }

        // 3. تعريف الخدمات
        $servicesData = [
            ['sub' => 'home_cleaning',   'type' => 'hourly', 'en' => 'Regular Home Cleaning',      'ar' => 'تنظيف منزلي عادي',   'price' => 80,  'period' => 2],
            ['sub' => 'home_cleaning',   'type' => 'hourly', 'en' => 'Deep Home Cleaning',         'ar' => 'تنظيف منزلي عميق',   'price' => 120, 'period' => 3],
            ['sub' => 'home_cleaning',   'type' => 'both',   'en' => 'Post-Construction Cleaning', 'ar' => 'تنظيف بعد التشطيب',  'price' => 150, 'period' => 4],
            ['sub' => 'home_cleaning',   'type' => 'hourly', 'en' => 'Move-in/Move-out Cleaning',  'ar' => 'تنظيف نقل العفش',    'price' => 130, 'period' => 2],
            ['sub' => 'carpet_cleaning', 'type' => 'hourly', 'en' => 'Sofa Deep Cleaning',          'ar' => 'تنظيف كنب عميق',     'price' => 60,  'period' => 2],
            ['sub' => 'carpet_cleaning', 'type' => 'hourly', 'en' => 'Carpet Washing',             'ar' => 'غسيل سجاد',          'price' => 50,  'period' => 2],
            ['sub' => 'child_care',      'type' => 'hourly', 'en' => 'Toddler Sitting',            'ar' => 'رعاية أطفال صغار',   'price' => 100, 'period' => 3],
            ['sub' => 'plumbing',        'type' => 'both',   'en' => 'Leak Detection & Repair',    'ar' => 'كشف وإصلاح تسريبات', 'price' => 150, 'period' => 2],
            ['sub' => 'electrical',      'type' => 'both',   'en' => 'Smart Home Setup',           'ar' => 'تأسيس أجهزة ذكية',   'price' => 250, 'period' => 2],
            ['sub' => 'hair_makeup',     'type' => 'both',   'en' => 'Pedicure & Manicure',        'ar' => 'بديكير ومنكير',      'price' => 150, 'period' => 1],
        ];

        // 4. إدخال الخدمات وتركيب القواعد والأسعار والمناطق المتعددة
        foreach ($servicesData as $index => $data) {
            $features = [
                ['en' => 'Dusting All Surfaces', 'ar' => 'مسح جميع الأسطح'],
                ['en' => 'Floor Cleaning',       'ar' => 'تنظيف الأرضيات'],
                ['en' => 'High-quality equipment', 'ar' => 'معدات عالية الجودة'],
            ];

            $service = Service::firstOrCreate(
                ['name_en' => $data['en']],
                [
                    'category_id'    => $subCategories[$data['sub']]->id,
                    'type'           => $data['type'],
                    'name_ar'        => $data['ar'],
                    'description_ar' => 'وصف تفصيلي لخدمة ' . $data['ar'],
                    'description_en' => 'Detailed description for ' . $data['en'],
                    'features'       => $features,
                    'status'         => 'active',
                ]
            );

            // تحديث جدول المدد للنظام الجديد (service_period_hours)
            DB::table('service_durations')->updateOrInsert(
                ['service_id' => $service->id],
                [
                    'service_period_hours' => $data['period'], 
                    'created_at'           => now(), 
                    'updated_at'           => now()
                ]
            );

            // إضافة القواعد الإدارية
            DB::table('service_rules')->updateOrInsert(
                ['service_id' => $service->id],
                [
                    'free_cancel_cutoff_hours' => 6,
                    'reschedule_cutoff_hours'  => 4,
                    'lead_time_min_hours'      => 3,
                    'lead_time_max_days'       => 14,
                    'late_cancel_action'       => 'fee',
                    'late_cancel_fee_type'     => 'fixed',
                    'late_cancel_fee_value'    => 50.00,
                    'no_show_policy'           => 'consumed',
                    'max_changes_per_booking'  => 2,
                    'created_at' => now(), 'updated_at' => now()
                ]
            );

            // 🌟 التعديل الجوهري: توزيع جيو-تسعيري ذكي (Geographical Distribution) 🌟
            // لضمان التنوع التام سنقوم بخلط النطاقات بناءً على مؤشر الخدمة ($index)
            if ($index % 2 === 0) {
                // السيناريو أ: الخدمة مغطاة ومسعرة على مستوى مدن متعددة (City Scope)
                foreach ($cities as $city) {
                    DB::table('service_coverages')->updateOrInsert(
                        ['service_id' => $service->id, 'city_id' => $city->id, 'area_id' => null],
                        ['status' => 'active', 'created_at' => now(), 'updated_at' => now()]
                    );

                    DB::table('service_prices')->updateOrInsert(
                        ['service_id' => $service->id, 'city_id' => $city->id, 'area_id' => null],
                        [
                            'pricing_scope'            => 'city',
                            'price_per_service_period' => $data['price'], // السعر الأساسي للكتلة
                            'currency'                 => 'EGP',
                            'created_at'               => now(), 
                            'updated_at'               => now()
                        ]
                    );
                }
            } else {
                // السيناريو ب: الخدمة مسعرة بشكل مخصص ومكثف على مستوى مناطق معينة (Area Scope)
                // نأخذ مصفوفة عشوائية من المناطق لنربط بها هذه الخدمة
                $randomAreas = $areas->random(min(3, $areas->count()));

                foreach ($randomAreas as $selectedArea) {
                    DB::table('service_coverages')->updateOrInsert(
                        ['service_id' => $service->id, 'city_id' => $selectedArea->city_id, 'area_id' => $selectedArea->id],
                        ['status' => 'active', 'created_at' => now(), 'updated_at' => now()]
                    );

                    // نغير السعر قليلاً (+- 10 أو 20) للمناطق المختلفة عشان تلاحظ الفرق عند الفحص بالبوستمان
                    $areaCustomPrice = $data['price'] + rand(-1, 2) * 10;

                    DB::table('service_prices')->updateOrInsert(
                        ['service_id' => $service->id, 'city_id' => $selectedArea->city_id, 'area_id' => $selectedArea->id],
                        [
                            'pricing_scope'            => 'area',
                            'price_per_service_period' => $areaCustomPrice, 
                            'currency'                 => 'EGP',
                            'created_at'               => now(), 
                            'updated_at'               => now()
                        ]
                    );
                }
            }

            // إضافة أوقات العمل للشفت (من 9 صباحاً لـ 5 مساءً)
            $workingDays = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday'];
            foreach ($workingDays as $day) {
                DB::table('service_schedules')->updateOrInsert(
                    [
                        'service_id' => $service->id, 
                        'day'        => $day, 
                        'start_time' => '09:00:00'
                    ],
                    [
                        'end_time'   => '17:00:00',
                        'is_active'  => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );

                 DB::table('service_schedules')->updateOrInsert(
                    [
                        'service_id' => $service->id, 
                        'day'        => $day, 
                        'start_time' => '18:00:00'
                    ],
                    [
                        'end_time'   => '23:00:00',
                        'is_active'  => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );
            }

            // رفع صور الميديا للخدمة
            $selectedIcon = Arr::random($dummyIconsList);
            $dummyIconPath = public_path('dummy/' . $selectedIcon);
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
                        'disk'      => 'public',
                        'path'      => $iconPath,
                        'size'      => Storage::disk('public')->size($iconPath),
                    ]
                );
            }
        }
        $this->command->info('Categories and Services seeded successfully with dynamic geographical distribution!');
    }
}