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
        // 1. جلب الخدمات الموجودة
        $services = Service::all();

        // 2. إنشاء 10 عمال نشطين لكل خدمة وتثبيت صورهم
        if ($services->count() > 0) {
            $image1 = public_path('assist/workers/1.webp');
            $image2 = public_path('assist/workers/2.webp');
            $imgIndex = 0;

            foreach ($services as $service) {
                $isFemaleOnly = in_array($service->service_code, ['DOMESTIC_HELPER_HOURLY', 'BABYSITTER_HOURLY', 'PEDICURE_MANICURE']);
                $isMaleOnly = ($service->service_code === 'DEEP_CLEANING');

                for ($i = 0; $i < 10; $i++) {
                    $gender = $isFemaleOnly ? 'female' : ($isMaleOnly ? 'male' : ($i % 2 === 0 ? 'female' : 'male'));

                    $worker = Worker::factory()->create([
                        'status' => 'active',
                        'gender' => $gender,
                    ]);

                    $worker->services()->syncWithoutDetaching([$service->id]);

                    // Seed worker images (round robin 1.webp & 2.webp)
                    $sourceImage = ($imgIndex % 2 === 0) ? $image1 : $image2;
                    $originalName = ($imgIndex % 2 === 0) ? '1.webp' : '2.webp';
                    $imgIndex++;

                    if (file_exists($sourceImage)) {
                        $targetDir = 'worker/image';
                        $fileName = 'worker_'.$worker->id.'_'.time().'_'.$i.'.webp';
                        $targetPath = $targetDir.'/'.$fileName;

                        Storage::disk('public')->put($targetPath, file_get_contents($sourceImage));

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
            }

            $this->command->info('تم إنشاء 10 عمال لكل خدمة وربطهم بالصور بنجاح.');
        } else {
            $this->command->error('لم يتم ربط العمال! جدول الخدمات (services) فارغ. يرجى تشغيل ServiceSeeder أولاً.');
        }
    }
}
