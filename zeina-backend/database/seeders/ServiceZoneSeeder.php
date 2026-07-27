<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceZone;
use Illuminate\Database\Seeder;

class ServiceZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zonesData = [
            [
                'name' => 'منطقة مدينة نصر ومصر الجديدة',
                'governorate' => 'القاهرة',
                'city' => 'مدينة نصر',
                'status' => 'active',
                'polygon_coordinates' => [
                    ['lat' => 30.0400, 'lng' => 31.3000],
                    ['lat' => 30.1000, 'lng' => 31.3000],
                    ['lat' => 30.1000, 'lng' => 31.3800],
                    ['lat' => 30.0400, 'lng' => 31.3800],
                ],
            ],
            [
                'name' => 'منطقة التجمع الخامس والنرجس',
                'governorate' => 'القاهرة',
                'city' => 'القاهرة الجديدة',
                'status' => 'active',
                'polygon_coordinates' => [
                    ['lat' => 29.9800, 'lng' => 31.4000],
                    ['lat' => 30.0600, 'lng' => 31.4000],
                    ['lat' => 30.0600, 'lng' => 31.5200],
                    ['lat' => 29.9800, 'lng' => 31.5200],
                ],
            ],
            [
                'name' => 'منطقة الرحاب والتجمع الأول',
                'governorate' => 'القاهرة',
                'city' => 'القاهرة الجديدة',
                'status' => 'active',
                'polygon_coordinates' => [
                    ['lat' => 30.0500, 'lng' => 31.4500],
                    ['lat' => 30.1200, 'lng' => 31.4500],
                    ['lat' => 30.1200, 'lng' => 31.5800],
                    ['lat' => 30.0500, 'lng' => 31.5800],
                ],
            ],
            [
                'name' => 'منطقة الشروق ومدينتي',
                'governorate' => 'القاهرة',
                'city' => 'الشروق',
                'status' => 'active',
                'polygon_coordinates' => [
                    ['lat' => 30.0800, 'lng' => 31.5500],
                    ['lat' => 30.1800, 'lng' => 31.5500],
                    ['lat' => 30.1800, 'lng' => 31.7000],
                    ['lat' => 30.0800, 'lng' => 31.7000],
                ],
            ],
            [
                'name' => 'منطقة العاصمة الإدارية الجديدة',
                'governorate' => 'القاهرة',
                'city' => 'العاصمة الإدارية',
                'status' => 'active',
                'polygon_coordinates' => [
                    ['lat' => 29.9500, 'lng' => 31.6000],
                    ['lat' => 30.0800, 'lng' => 31.6000],
                    ['lat' => 30.0800, 'lng' => 31.8000],
                    ['lat' => 29.9500, 'lng' => 31.8000],
                ],
            ],
            [
                'name' => 'منطقة المعادي والزهراء',
                'governorate' => 'القاهرة',
                'city' => 'المعادي',
                'status' => 'active',
                'polygon_coordinates' => [
                    ['lat' => 29.9300, 'lng' => 31.2300],
                    ['lat' => 29.9900, 'lng' => 31.2300],
                    ['lat' => 29.9900, 'lng' => 31.3100],
                    ['lat' => 29.9300, 'lng' => 31.3100],
                ],
            ],
            [
                'name' => 'منطقة المقطم والهضبة الوسطى',
                'governorate' => 'القاهرة',
                'city' => 'المقطم',
                'status' => 'active',
                'polygon_coordinates' => [
                    ['lat' => 30.0000, 'lng' => 31.2700],
                    ['lat' => 30.0500, 'lng' => 31.2700],
                    ['lat' => 30.0500, 'lng' => 31.3400],
                    ['lat' => 30.0000, 'lng' => 31.3400],
                ],
            ],
            [
                'name' => 'منطقة 6 أكتوبر - أحياء المركزية',
                'governorate' => 'الجيزة',
                'city' => '6 أكتوبر',
                'status' => 'active',
                'polygon_coordinates' => [
                    ['lat' => 29.9200, 'lng' => 30.8800],
                    ['lat' => 30.0100, 'lng' => 30.8800],
                    ['lat' => 30.0100, 'lng' => 31.0200],
                    ['lat' => 29.9200, 'lng' => 31.0200],
                ],
            ],
            [
                'name' => 'منطقة حدائق الأهرام والرماية',
                'governorate' => 'الجيزة',
                'city' => 'حدائق الأهرام',
                'status' => 'active',
                'polygon_coordinates' => [
                    ['lat' => 29.9600, 'lng' => 31.0800],
                    ['lat' => 30.0100, 'lng' => 31.0800],
                    ['lat' => 30.0100, 'lng' => 31.1500],
                    ['lat' => 29.9600, 'lng' => 31.1500],
                ],
            ],
            [
                'name' => 'منطقة الشيخ زايد والحي المتميز',
                'governorate' => 'الجيزة',
                'city' => 'الشيخ زايد',
                'status' => 'active',
                'polygon_coordinates' => [
                    ['lat' => 30.0100, 'lng' => 30.9300],
                    ['lat' => 30.0800, 'lng' => 30.9300],
                    ['lat' => 30.0800, 'lng' => 31.0300],
                    ['lat' => 30.0100, 'lng' => 30.9300],
                ],
            ],
            [
                'name' => 'منطقة الدقي والمهندسين والزمالك',
                'governorate' => 'الجيزة',
                'city' => 'الدقي والمهندسين',
                'status' => 'active',
                'polygon_coordinates' => [
                    ['lat' => 30.0200, 'lng' => 31.1800],
                    ['lat' => 30.0700, 'lng' => 31.1800],
                    ['lat' => 30.0700, 'lng' => 31.2300],
                    ['lat' => 30.0200, 'lng' => 31.2300],
                ],
            ],
            [
                'name' => 'منطقة العبور والعبور الجديدة',
                'governorate' => 'القليوبية',
                'city' => 'العبور',
                'status' => 'active',
                'polygon_coordinates' => [
                    ['lat' => 30.2000, 'lng' => 31.4200],
                    ['lat' => 30.2800, 'lng' => 31.4200],
                    ['lat' => 30.2800, 'lng' => 31.5500],
                    ['lat' => 30.2000, 'lng' => 31.5500],
                ],
            ],
            [
                'name' => 'منطقة سموحة وشرق الإسكندرية',
                'governorate' => 'الإسكندرية',
                'city' => 'سموحة',
                'status' => 'active',
                'polygon_coordinates' => [
                    ['lat' => 31.1800, 'lng' => 29.9000],
                    ['lat' => 31.2600, 'lng' => 29.9000],
                    ['lat' => 31.2600, 'lng' => 30.0200],
                    ['lat' => 31.1800, 'lng' => 30.0200],
                ],
            ],
            [
                'name' => 'منطقة العجمي والساحل الشمالي',
                'governorate' => 'الإسكندرية',
                'city' => 'العجمي',
                'status' => 'active',
                'polygon_coordinates' => [
                    ['lat' => 31.0800, 'lng' => 29.7000],
                    ['lat' => 31.1600, 'lng' => 29.7000],
                    ['lat' => 31.1600, 'lng' => 29.8500],
                    ['lat' => 31.0800, 'lng' => 29.8500],
                ],
            ],
            [
                'name' => 'منطقة المنصورة والجامعة',
                'governorate' => 'الدقهلية',
                'city' => 'المنصورة',
                'status' => 'active',
                'polygon_coordinates' => [
                    ['lat' => 31.0100, 'lng' => 31.3500],
                    ['lat' => 31.0800, 'lng' => 31.3500],
                    ['lat' => 31.0800, 'lng' => 31.4500],
                    ['lat' => 31.0100, 'lng' => 31.4500],
                ],
            ],
            [
                'name' => 'منطقة طنطا والمركز',
                'governorate' => 'الغربية',
                'city' => 'طنطا',
                'status' => 'active',
                'polygon_coordinates' => [
                    ['lat' => 30.7600, 'lng' => 30.9800],
                    ['lat' => 30.8300, 'lng' => 30.9800],
                    ['lat' => 30.8300, 'lng' => 31.0600],
                    ['lat' => 30.7600, 'lng' => 31.0600],
                ],
            ],
        ];

        $services = Service::all();
        $serviceIds = $services->pluck('id')->toArray();

        foreach ($zonesData as $data) {
            $zone = ServiceZone::updateOrCreate(
                ['name' => $data['name']],
                $data
            );

            // Link all active services to every service zone
            if (!empty($serviceIds)) {
                $zone->services()->syncWithoutDetaching($serviceIds);
            }
        }
    }
}
