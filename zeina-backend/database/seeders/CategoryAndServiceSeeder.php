<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Service;
use App\Models\City;
use App\Models\Area;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr; // 🌟 إضافة هذا السطر للاختيار العشوائي

class CategoryAndServiceSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first(); 
        $city = City::first();  
        $area = Area::where('city_id', $city->id ?? 0)->first();

        // ---------------------------------------------------------
        // 1. تعريف الأقسام الرئيسية (Main Categories)
        // ---------------------------------------------------------
        $mainCategoriesData = [
            'cleaning'    => ['en' => 'Cleaning', 'ar' => 'تنظيف'],
            'babysitting' => ['en' => 'Babysitting', 'ar' => 'رعاية أطفال'],
            'maintenance' => ['en' => 'Maintenance', 'ar' => 'صيانة منزلية'],
            'beauty'      => ['en' => 'Beauty & Spa', 'ar' => 'تجميل وعناية'],
        ];

        $mainCategories = [];
        foreach ($mainCategoriesData as $key => $data) {
            $mainCategories[$key] = Category::firstOrCreate(
                ['name_en' => $data['en'], 'parent_id' => null],
                ['name_ar' => $data['ar'], 'status' => 'active', 'created_by' => $admin->id ?? null]
            );
        }

        // ---------------------------------------------------------
        // 2. تعريف الأقسام الفرعية (Subcategories)
        // ---------------------------------------------------------
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
            $subCategories[$key] = Category::firstOrCreate(
                ['name_en' => $data['en'], 'parent_id' => $mainCategories[$data['parent']]->id],
                ['name_ar' => $data['ar'], 'status' => 'active', 'created_by' => $admin->id ?? null]
            );
        }

        // ---------------------------------------------------------
        // 3. تعريف الخدمات (20 Service)
        // ---------------------------------------------------------
        $servicesData = [
            ['sub' => 'home_cleaning', 'type' => 'hourly', 'en' => 'Regular Home Cleaning', 'ar' => 'تنظيف منزلي عادي', 'price' => 80],
            ['sub' => 'home_cleaning', 'type' => 'hourly', 'en' => 'Deep Home Cleaning', 'ar' => 'تنظيف منزلي عميق', 'price' => 120],
            ['sub' => 'home_cleaning', 'type' => 'both', 'en' => 'Post-Construction Cleaning', 'ar' => 'تنظيف بعد التشطيب', 'price' => 150],
            ['sub' => 'home_cleaning', 'type' => 'hourly', 'en' => 'Move-in/Move-out Cleaning', 'ar' => 'تنظيف نقل العفش', 'price' => 130],
            ['sub' => 'carpet_cleaning', 'type' => 'hourly', 'en' => 'Sofa Deep Cleaning', 'ar' => 'تنظيف كنب عميق', 'price' => 60],
            ['sub' => 'carpet_cleaning', 'type' => 'hourly', 'en' => 'Carpet Washing', 'ar' => 'غسيل سجاد', 'price' => 50],
            ['sub' => 'carpet_cleaning', 'type' => 'hourly', 'en' => 'Mattress Sanitization', 'ar' => 'تعقيم مراتب', 'price' => 70],
            ['sub' => 'carpet_cleaning', 'type' => 'both', 'en' => 'Curtain Steam Cleaning', 'ar' => 'تنظيف ستائر بالبخار', 'price' => 90],
            ['sub' => 'child_care', 'type' => 'hourly', 'en' => 'Toddler Sitting', 'ar' => 'رعاية أطفال صغار', 'price' => 100],
            ['sub' => 'child_care', 'type' => 'hourly', 'en' => 'Newborn Care', 'ar' => 'رعاية حديثي الولادة', 'price' => 150],
            ['sub' => 'child_care', 'type' => 'moqema', 'en' => 'Live-in Nanny', 'ar' => 'مربية مقيمة', 'price' => 3000],
            ['sub' => 'child_care', 'type' => 'hourly', 'en' => 'Special Needs Care', 'ar' => 'رعاية ذوي الاحتياجات', 'price' => 200],
            ['sub' => 'plumbing', 'type' => 'both', 'en' => 'Leak Detection & Repair', 'ar' => 'كشف وإصلاح تسريبات', 'price' => 150],
            ['sub' => 'plumbing', 'type' => 'both', 'en' => 'Water Heater Installation', 'ar' => 'تركيب سخان مياه', 'price' => 200],
            ['sub' => 'plumbing', 'type' => 'both', 'en' => 'Drain Unclogging', 'ar' => 'تسليك مجاري', 'price' => 100],
            ['sub' => 'electrical', 'type' => 'both', 'en' => 'Light Fixture Installation', 'ar' => 'تركيب وحدات إضاءة', 'price' => 70],
            ['sub' => 'electrical', 'type' => 'hourly', 'en' => 'Wiring Repair', 'ar' => 'إصلاح تمديدات كهربائية', 'price' => 120],
            ['sub' => 'electrical', 'type' => 'both', 'en' => 'Smart Home Setup', 'ar' => 'تأسيس أجهزة ذكية', 'price' => 250],
            ['sub' => 'hair_makeup', 'type' => 'both', 'en' => 'Bridal Makeup', 'ar' => 'مكياج عرائس', 'price' => 500],
            ['sub' => 'hair_makeup', 'type' => 'both', 'en' => 'Pedicure & Manicure', 'ar' => 'بديكير ومنكير', 'price' => 150],
        ];

        // 🌟 قوائم الصور العشوائية الموجودة في public/dummy
        $dummyIconsList = ['icon_1.jpg', 'icon_2.jpg', 'icon_3.jpg','cover_1.jpg', 'cover_2.jpg', 'cover_3.jpg'];
        // $dummyCoversList = ['cover_1.jpg', 'cover_2.jpg', 'cover_3.jpg'];

        // ---------------------------------------------------------
        // 4. إدخال الخدمات وتركيب قواعدها وأسعارها وجداولها (Loop)
        // ---------------------------------------------------------
        foreach ($servicesData as $data) {

            $features = [
                ['en' => 'Dusting All Surfaces', 'ar' => 'مسح جميع الأسطح'],
                ['en' => 'Floor Cleaning', 'ar' => 'تنظيف الأرضيات'],
                ['en' => 'High-quality equipment', 'ar' => 'معدات عالية الجودة'],
            ];

            $service = Service::firstOrCreate(
                ['name_en' => $data['en']],
                [
                    'category_id' => $subCategories[$data['sub']]->id,
                    'type' => $data['type'],
                    'name_ar' => $data['ar'],
                    'description_ar' => 'وصف تفصيلي لخدمة ' . $data['ar'],
                    'description_en' => 'Detailed description for ' . $data['en'],
                    'features' => $features,
                    'status' => 'active',
                ]
            );

            // إضافة المدد الزمنية
            DB::table('service_durations')->updateOrInsert(
                ['service_id' => $service->id],
                [
                    'allowed_durations' => json_encode([2, 4, 6, 8]),
                    'min_duration_hours' => 2,
                    'created_at' => now(), 'updated_at' => now()
                ]
            );

            // إضافة القواعد
            DB::table('service_rules')->updateOrInsert(
                ['service_id' => $service->id],
                [
                    'free_cancel_cutoff_hours' => 6,
                    'reschedule_cutoff_hours' => 4,
                    'lead_time_min_hours' => 3,
                    'lead_time_max_days' => 14,
                    'late_cancel_action' => 'fee',
                    'late_cancel_fee_type' => 'fixed',
                    'late_cancel_fee_value' => 50.00,
                    'no_show_policy' => 'consumed',
                    'max_changes_per_booking' => 2,
                    'created_at' => now(), 'updated_at' => now()
                ]
            );

            // التغطية والتسعير
            if ($city) {
                DB::table('service_coverages')->updateOrInsert(
                    ['service_id' => $service->id, 'city_id' => $city->id, 'area_id' => $area->id ?? null],
                    ['status' => 'active', 'created_at' => now(), 'updated_at' => now()]
                );

                DB::table('service_prices')->updateOrInsert(
                    ['service_id' => $service->id, 'city_id' => $city->id, 'area_id' => $area->id ?? null],
                    [
                        'pricing_scope' => $area ? 'area' : 'city',
                        'base_price' => 50.00,
                        'price_per_hour' => $data['price'], 
                        'currency' => 'EGP',
                        'created_at' => now(), 'updated_at' => now()
                    ]
                );
            }

            // إضافة أوقات العمل (Schedules)
            $workingDays = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday'];
            foreach ($workingDays as $day) {
                DB::table('service_schedules')->updateOrInsert(
                    [
                        'service_id' => $service->id, 
                        'day' => $day, 
                        'start_time' => '09:00:00'
                    ],
                    [
                        'end_time' => '17:00:00',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );
            }

            // ---------------------------------------------------------
            // 🌟 5. إضافة الصور التجريبية العشوائية 🌟
            // ---------------------------------------------------------
            
            // اختيار صور عشوائية من القوائم
            $selectedIcon = Arr::random($dummyIconsList);
            // $selectedCover = Arr::random($dummyCoversList);

            // المسارات في مجلد public
            $dummyIconPath = public_path('dummy/' . $selectedIcon);
            // $dummyCoverPath = public_path('dummy/' . $selectedCover);

            // حفظ الأيقونة العشوائية
            if (file_exists($dummyIconPath)) {
                $iconName = 'service_' . $service->id . '_icon.jpg';
                $iconPath = 'services/icons/' . $iconName;
                
                Storage::disk('public')->put($iconPath, file_get_contents($dummyIconPath));
                
                $service->media()->updateOrCreate(
                    ['collection' => 'service/icon'],
                    [
                        'file_name'  => $iconName,
                        'mime_type'  => 'image/jpeg',
                        'disk'       => 'public',
                        'path'       => $iconPath,
                        'size'       => Storage::disk('public')->size($iconPath),
                    ]
                );
            }

            // حفظ الغلاف العشوائي
            // if (file_exists($dummyCoverPath)) {
            //     $coverName = 'service_' . $service->id . '_cover.jpg';
            //     $coverPath = 'services/covers/' . $coverName;
                
            //     Storage::disk('public')->put($coverPath, file_get_contents($dummyCoverPath));
                
            //     $service->media()->updateOrCreate(
            //         ['collection' => 'service/cover'],
            //         [
            //             'file_name'  => $coverName,
            //             'mime_type'  => 'image/jpeg',
            //             'disk'       => 'public',
            //             'path'       => $coverPath,
            //             'size'       => Storage::disk('public')->size($coverPath),
            //         ]
            //     );
            // }
        }
    }
}