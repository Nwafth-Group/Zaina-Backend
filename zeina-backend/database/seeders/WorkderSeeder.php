<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\Worker;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WorkderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
   public function run(): void
    {
        // 1. إنشاء 30 عامل
        $workers = Worker::factory(30)->create();

        // 2. جلب الخدمات الموجودة
        $services = Service::all();
        $serviceCount = $services->count();

        // 3. الربط فقط إذا كان في خدمات
        if ($serviceCount > 0) {
            foreach ($workers as $worker) {
                // نحدد الحد الأقصى للاختيار (ما يتجاوز 3 وما يتجاوز عدد الخدمات الفعلي)
                $maxToSelect = min(3, $serviceCount); 
                
                // نختار عدد عشوائي بين 1 والحد الأقصى
                $randomServices = $services->random(rand(1, $maxToSelect))->pluck('id')->toArray();
                
                $worker->services()->attach($randomServices);
            }
        } else {
            // رسالة تظهر في التيرمنال إذا ما كان في خدمات
            $this->command->info('تم إنشاء العمال، لكن لم يتم ربطهم لأن جدول الخدمات (services) فارغ!');
        }
    }
}
