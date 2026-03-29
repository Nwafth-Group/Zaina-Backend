<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\City;
use App\Models\Country;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. إضافة دولة مصر
        $egypt = Country::firstOrCreate(
            ['code' => 'EG'],
            ['name_ar' => 'مصر', 'name_en' => 'Egypt', 'status' => 'active', 'flag'=> 'images/flags/egupt.png' ,'phone_code'=>'+999' , 'currency_code'=>'EGP']
        );

        // إضافة مدن لمصر
        $cairo = City::firstOrCreate(
            ['code' => 'CAI', 'country_id' => $egypt->id],
            ['name_ar' => 'القاهرة', 'name_en' => 'Cairo', 'status' => 'active']
        );

        $giza = City::firstOrCreate(
            ['code' => 'GIZ', 'country_id' => $egypt->id],
            ['name_ar' => 'الجيزة', 'name_en' => 'Giza', 'status' => 'active']
        );

        // إضافة مناطق للقاهرة
        Area::firstOrCreate(
            ['code' => 'MAA', 'city_id' => $cairo->id],
            ['name_ar' => 'المعادي', 'name_en' => 'Maadi', 'status' => 'active']
        );

        Area::firstOrCreate(
            ['code' => 'NAS', 'city_id' => $cairo->id],
            ['name_ar' => 'مدينة نصر', 'name_en' => 'Nasr City', 'status' => 'active']
        );

        // إضافة مناطق للجيزة
        Area::firstOrCreate(
            ['code' => 'DOK', 'city_id' => $giza->id],
            ['name_ar' => 'الدقي', 'name_en' => 'Dokki', 'status' => 'active']
        );

        // 2. إضافة المملكة العربية السعودية
        $saudi = Country::firstOrCreate(
            ['code' => 'SA'],
            ['name_ar' => 'المملكة العربية السعودية', 'flag'=> 'images/flags/ksa.png' , 'name_en' => 'Saudi Arabia', 'status' => 'active' , 'phone_code'=>'+777' , 'currency_code'=>'SA']
        );

        // إضافة مدن للسعودية
        $riyadh = City::firstOrCreate(
            ['code' => 'RUH', 'country_id' => $saudi->id],
            ['name_ar' => 'الرياض', 'name_en' => 'Riyadh', 'status' => 'active']
        );

        $jeddah = City::firstOrCreate(
            ['code' => 'JED', 'country_id' => $saudi->id],
            ['name_ar' => 'جدة', 'name_en' => 'Jeddah', 'status' => 'active']
        );

        // إضافة مناطق للرياض
        Area::firstOrCreate(
            ['code' => 'MAL', 'city_id' => $riyadh->id],
            ['name_ar' => 'الملز', 'name_en' => 'Al Malaz', 'status' => 'active']
        );

        Area::firstOrCreate(
            ['code' => 'OLAY', 'city_id' => $riyadh->id],
            ['name_ar' => 'العليا', 'name_en' => 'Olaya', 'status' => 'active']
        );
    }
}
