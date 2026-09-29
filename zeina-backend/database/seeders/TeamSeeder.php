<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\Team;
use App\Models\Worker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Get services 6 to 11 (team-based services)
        $teamCodes = Service::getTeamBasedCodes();
        $specializedServices = Service::whereIn('service_code', $teamCodes)
            ->orWhereIn(DB::raw('LOWER(service_code)'), array_map('strtolower', $teamCodes))
            ->get();

        if ($specializedServices->isEmpty()) {
            $this->command->warn('No specialized services (6 to 11) found. Please seed services first.');
            return;
        }

        // 2. Define 8 realistic operational teams
        $teamsData = [
            [
                'code' => 'TEAM-01',
                'name_ar' => 'فريق التنظيف الفندقي المتخصص 1',
                'name_en' => 'Specialized Hospitality Cleaning Team 1',
                'status' => 'active',
                'notes' => 'فريق متخصص لتنظيف الكنب والمجالس وخزانات المياه',
                'workers_count' => 3,
            ],
            [
                'code' => 'TEAM-02',
                'name_ar' => 'فريق صيانة وغسيل المكيفات والأسطح',
                'name_en' => 'AC & Surface Sanitation Team',
                'status' => 'active',
                'notes' => 'فريق معتمد لغسيل وصيانة المكيفات وتنظيف الأسطح والدرج',
                'workers_count' => 2,
            ],
            [
                'code' => 'TEAM-03',
                'name_ar' => 'فريق غسيل السجاد والمفروشات بالبخار',
                'name_en' => 'Steam Carpet & Upholstery Team',
                'status' => 'active',
                'notes' => 'فريق مجهز بأجهزة البخار الحديثة لغسيل الموكيت والسجاد',
                'workers_count' => 4,
            ],
            [
                'code' => 'TEAM-04',
                'name_ar' => 'فريق تعقيم وتطهير خزانات المياه',
                'name_en' => 'Water Tank Disinfection Team',
                'status' => 'active',
                'notes' => 'فريق فني متخصص بتطهير وعزل خزانات مياه الشرب',
                'workers_count' => 3,
            ],
            [
                'code' => 'TEAM-05',
                'name_ar' => 'فريق العناية المتنقلة وغسيل السيارات',
                'name_en' => 'Mobile Vehicle Care & Detailing Team',
                'status' => 'active',
                'notes' => 'فريق ميداني مجهز لغسيل وتلميع المركبات والسيارات',
                'workers_count' => 2,
            ],
            [
                'code' => 'TEAM-06',
                'name_ar' => 'فريق تنظيف الواجهات والأسطح الخارجية',
                'name_en' => 'Façade & Outdoor Cleaning Team',
                'status' => 'active',
                'notes' => 'فريق مخصص للمداخل والممرات والأسطح الخارجية',
                'workers_count' => 5,
            ],
            [
                'code' => 'TEAM-07',
                'name_ar' => 'فريق التنظيف العميق والشامل',
                'name_en' => 'Comprehensive Deep Cleaning Team',
                'status' => 'active',
                'notes' => 'فريق متكامل للمشاريع الكبيرة والفلل والشقق السكنية',
                'workers_count' => 4,
            ],
            [
                'code' => 'TEAM-08',
                'name_ar' => 'فريق التدخل السريع والطوارئ',
                'name_en' => 'Rapid Response Operations Team',
                'status' => 'active',
                'notes' => 'فريق جاهز لطلبات الحجز المستعجلة والخدمات التخصصية',
                'workers_count' => 3,
            ],
        ];

        $serviceIds = $specializedServices->pluck('id')->toArray();

        foreach ($teamsData as $tData) {
            $team = Team::firstOrCreate(
                ['code' => $tData['code']],
                [
                    'name_ar' => $tData['name_ar'],
                    'name_en' => $tData['name_en'],
                    'status' => $tData['status'],
                    'notes' => $tData['notes'],
                ]
            );

            // Ensure team details are up to date
            $team->update([
                'name_ar' => $tData['name_ar'],
                'name_en' => $tData['name_en'],
                'status' => $tData['status'],
                'notes' => $tData['notes'],
            ]);

            // Desired workers for this team
            $targetCount = $tData['workers_count'] ?? 3;
            $currentWorkers = $team->workers()->where('status', 'active')->get();
            $neededCount = $targetCount - $currentWorkers->count();

            $assignedWorkerIds = $currentWorkers->pluck('id')->toArray();

            if ($neededCount > 0) {
                // Fetch random male workers who are not assigned to ANY team
                $availableWorkers = Worker::where('status', 'active')
                    ->where('gender', 'male')
                    ->whereNull('team_id')
                    ->inRandomOrder()
                    ->take($neededCount)
                    ->get();

                // If not enough unassigned male workers exist, create the remaining
                $remainingNeeded = $neededCount - $availableWorkers->count();
                $newWorkers = collect();
                for ($i = 0; $i < $remainingNeeded; $i++) {
                    $newWorker = Worker::factory()->create([
                        'status' => 'active',
                        'gender' => 'male',
                        'team_id' => null,
                    ]);
                    $newWorkers->push($newWorker);
                }

                $workersToAssign = $availableWorkers->concat($newWorkers);

                foreach ($workersToAssign as $worker) {
                    $worker->update([
                        'team_id' => $team->id,
                        'gender' => 'male',
                    ]);
                    $assignedWorkerIds[] = $worker->id;
                }
            }

            // Bind team to specialized services (6 to 11)
            $team->services()->syncWithoutDetaching($serviceIds);

            // Bind all team workers to all specialized services
            foreach ($specializedServices as $srv) {
                $srv->workers()->syncWithoutDetaching($assignedWorkerIds);
            }

            $this->command->info("Seeded Team: {$team->name_ar} ({$team->code}) with " . count($assignedWorkerIds) . " workers (assigned exclusively to this team) and linked to " . count($serviceIds) . " services.");
        }
    }
}
