<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Country;
use App\Models\Govrenrate; // تأكد من اسم الموديل عندك
use App\Models\City;
use App\Models\Area;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        // 🌟 1. إنشاء الدول (Countries)
        $egypt = Country::firstOrCreate(
            ['code' => 'EG'],
            [
                'name_ar'       => 'مصر', 
                'name_en'       => 'Egypt', 
                'currency_code' => 'EGP',
                'phone_code'    => '+20',
                'flag'          => 'eg_flag.png', // أو إيموجي 🇪🇬
                'status'        => 'active'
            ]
        );

        $ksa = Country::firstOrCreate(
            ['code' => 'SA'],
            [
                'name_ar'       => 'السعودية', 
                'name_en'       => 'Saudi Arabia', 
                'currency_code' => 'SAR',
                'phone_code'    => '+966',
                'flag'          => 'sa_flag.png', // أو إيموجي 🇸🇦
                'status'        => 'active'
            ]
        );

        // ---------------------------------------------------------
        // 🇪🇬 بيانات مصر
        // ---------------------------------------------------------
        
        // 🌟 2. المحافظات (Govrenrates)
        $cairoGov = Govrenrate::firstOrCreate(
            ['country_id' => $egypt->id, 'name_en' => 'Cairo'],
            ['name_ar' => 'القاهرة', 'status' => 'active']
        );

        $gizaGov = Govrenrate::firstOrCreate(
            ['country_id' => $egypt->id, 'name_en' => 'Giza'],
            ['name_ar' => 'الجيزة', 'status' => 'active']
        );

        // 🌟 3. المدن والمناطق (Cities & Areas) - محافظة القاهرة
        
        // مدينة نصر
        $nasrCity = City::firstOrCreate(
            ['govrenrate_id' => $cairoGov->id, 'name_en' => 'Nasr City'],
            ['name_ar' => 'مدينة نصر', 'code' => 'CAI-NASR', 'status' => 'active']
        );
        
        $nasrCityAreas = [
            ['ar' => 'الحي الأول', 'en' => 'First District', 'code' => 'NASR-01'],
            ['ar' => 'الحي السابع', 'en' => 'Seventh District', 'code' => 'NASR-07'],
            ['ar' => 'مكرم عبيد', 'en' => 'Makram Ebeid', 'code' => 'NASR-MAK'],
            ['ar' => 'عباس العقاد', 'en' => 'Abbas El Akkad', 'code' => 'NASR-ABB'],
        ];
        foreach ($nasrCityAreas as $area) {
            Area::firstOrCreate(
                ['city_id' => $nasrCity->id, 'name_en' => $area['en']],
                ['name_ar' => $area['ar'], 'code' => $area['code'], 'status' => 'active']
            );
        }

        // القاهرة الجديدة (التجمع)
        $newCairo = City::firstOrCreate(
            ['govrenrate_id' => $cairoGov->id, 'name_en' => 'New Cairo'],
            ['name_ar' => 'القاهرة الجديدة', 'code' => 'CAI-NEW', 'status' => 'active']
        );

        $newCairoAreas = [
            ['ar' => 'التجمع الخامس', 'en' => 'Fifth Settlement', 'code' => 'NEW-5'],
            ['ar' => 'التجمع الأول', 'en' => 'First Settlement', 'code' => 'NEW-1'],
            ['ar' => 'الرحاب', 'en' => 'Al Rehab', 'code' => 'NEW-REH'],
        ];
        foreach ($newCairoAreas as $area) {
            Area::firstOrCreate(
                ['city_id' => $newCairo->id, 'name_en' => $area['en']],
                ['name_ar' => $area['ar'], 'code' => $area['code'], 'status' => 'active']
            );
        }

        // 🌟 4. المدن والمناطق - محافظة الجيزة
        $octoberCity = City::firstOrCreate(
            ['govrenrate_id' => $gizaGov->id, 'name_en' => '6th of October'],
            ['name_ar' => 'السادس من أكتوبر', 'code' => 'GZ-6OCT', 'status' => 'active']
        );

        $octoberAreas = [
            ['ar' => 'الحي المتميز', 'en' => 'Al Motamayez District', 'code' => 'OCT-MOT'],
            ['ar' => 'ميدان الحصري', 'en' => 'Hosary Square', 'code' => 'OCT-HOS'],
            ['ar' => 'الشيخ زايد', 'en' => 'Sheikh Zayed', 'code' => 'OCT-ZAY'],
        ];
        foreach ($octoberAreas as $area) {
            Area::firstOrCreate(
                ['city_id' => $octoberCity->id, 'name_en' => $area['en']],
                ['name_ar' => $area['ar'], 'code' => $area['code'], 'status' => 'active']
            );
        }

        // ---------------------------------------------------------
        // 🇸🇦 بيانات السعودية (مثال إضافي)
        // ---------------------------------------------------------
        
        $riyadhGov = Govrenrate::firstOrCreate(
            ['country_id' => $ksa->id, 'name_en' => 'Riyadh Region'],
            ['name_ar' => 'منطقة الرياض', 'status' => 'active']
        );

        $riyadhCity = City::firstOrCreate(
            ['govrenrate_id' => $riyadhGov->id, 'name_en' => 'Riyadh'],
            ['name_ar' => 'الرياض', 'code' => 'RUH-CITY', 'status' => 'active']
        );

        $riyadhAreas = [
            ['ar' => 'العليا', 'en' => 'Al Olaya', 'code' => 'RUH-OLY'],
            ['ar' => 'الملز', 'en' => 'Al Malaz', 'code' => 'RUH-MLZ'],
            ['ar' => 'السليمانية', 'en' => 'As Sulimaniyah', 'code' => 'RUH-SUL'],
        ];
        foreach ($riyadhAreas as $area) {
            Area::firstOrCreate(
                ['city_id' => $riyadhCity->id, 'name_en' => $area['en']],
                ['name_ar' => $area['ar'], 'code' => $area['code'], 'status' => 'active']
            );
        }
    }
}