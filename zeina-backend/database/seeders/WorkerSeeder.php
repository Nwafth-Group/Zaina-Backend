<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\Worker;
use Illuminate\Database\Seeder;

class WorkerSeeder extends Seeder
{
    public function run(): void
    {
        // 1. إنشاء 30 عامل والتأكد أن حالتهم نشطة
        $workers = Worker::factory(30)->create(['status' => 'active']);

        // 2. جلب الخدمات الموجودة
        $services = Service::all();
        $serviceCount = $services->count();

        // 3. الربط بذكاء
        if ($serviceCount > 0) {
            foreach ($workers as $worker) {
                // نضمن أن العامل مربوط بـ 1 إلى 4 خدمات كحد أقصى لتكون البيانات واقعية
                $maxToSelect = min(4, $serviceCount); 
                
                $randomServices = $services->random(rand(1, $maxToSelect))->pluck('id')->toArray();
                
                $worker->services()->attach($randomServices);
            }
            $this->command->info('تم إنشاء 30 عاملاً وربطهم بالخدمات بنجاح.');
        } else {
            $this->command->error('لم يتم ربط العمال! جدول الخدمات (services) فارغ. يرجى تشغيل ServiceSeeder أولاً.');
        }
    }
}