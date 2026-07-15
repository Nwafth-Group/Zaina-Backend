<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\Worker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

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
            $image1 = public_path('assist/workers/1.webp');
            $image2 = public_path('assist/workers/2.webp');

            foreach ($workers as $index => $worker) {
                $randomService = $services->random();
                if ($randomService->service_code === 'DOMESTIC_HELPER_HOURLY' || $randomService->service_code === 'BABYSITTER_HOURLY') {
                    $worker->update(['gender' => 'female']);
                }
                $worker->services()->attach([$randomService->id]);

                // Seed worker images (round robin 1.webp & 2.webp)
                $sourceImage = ($index % 2 === 0) ? $image1 : $image2;
                $originalName = ($index % 2 === 0) ? '1.webp' : '2.webp';

                if (file_exists($sourceImage)) {
                    $targetDir = 'worker/image';
                    $fileName = 'worker_'.$worker->id.'_'.time().'.webp';
                    $targetPath = $targetDir.'/'.$fileName;

                    // Copy to storage
                    Storage::disk('public')->put($targetPath, file_get_contents($sourceImage));

                    // Create media relationship
                    $worker->media()->create([
                        'collection' => 'worker/image',
                        'file_name' => $fileName,
                        'mime_type' => 'image/webp',
                        'size' => filesize($sourceImage),
                        'disk' => 'public',
                        'path' => $targetPath,
                        'title' => $originalName,
                        'date' => now()->toDateString(),
                    ]);
                }
            }
            $this->command->info('تم إنشاء 30 عاملاً وربط كل منهم بخدمة واحدة بنجاح مع الصور.');
        } else {
            $this->command->error('لم يتم ربط العمال! جدول الخدمات (services) فارغ. يرجى تشغيل ServiceSeeder أولاً.');
        }
    }
}
