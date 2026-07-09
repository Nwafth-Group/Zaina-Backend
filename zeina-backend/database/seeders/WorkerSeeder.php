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

        // 3. الربط بذكاء (عامل واحد لخدمة واحدة فقط)
        if ($serviceCount > 0) {
            foreach ($workers as $worker) {
                $randomService = $services->random();
                $worker->services()->attach([$randomService->id]);
            }
            $this->command->info('تم إنشاء 30 عاملاً وربط كل منهم بخدمة واحدة بنجاح.');
        } else {
            $this->command->error('لم يتم ربط العمال! جدول الخدمات (services) فارغ. يرجى تشغيل ServiceSeeder أولاً.');
        }
    }
}