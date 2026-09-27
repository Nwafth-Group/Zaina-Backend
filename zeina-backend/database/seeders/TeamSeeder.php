<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\Team;
use App\Models\Worker;
use Illuminate\Database\Seeder;

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
            ->orWhereIn(\Illuminate\Support\Facades\DB::raw('LOWER(service_code)'), array_map('strtolower', $teamCodes))
            ->get();

        if ($specializedServices->isEmpty()) {
            $this->command->warn('No specialized services (6 to 11) found. Please seed services first.');
            return;
        }

        // 2. Sample teams data
        $teamsData = [
            [
                'name_ar' => 'فريق الدعم الفني والتنظيف 1',
                'name_en' => 'Specialized Cleaning Team 1',
                'code' => 'TEAM-01',
                'status' => 'active',
                'notes' => 'فريق مدرب على تنظيف الكنب وخزانات المياه والأسطح',
                'workers' => [
                    [
                        'name' => 'فهد العنزي',
                        'phone_number' => '0599200001',
                        'nationality' => 'Saudi Arabia',
                        'id_number' => '1099200001',
                        'gender' => 'male',
                        'address' => 'الرياض - حي النفل',
                    ],
                    [
                        'name' => 'خالد الدوسري',
                        'phone_number' => '0599200002',
                        'nationality' => 'Saudi Arabia',
                        'id_number' => '1099200002',
                        'gender' => 'male',
                        'address' => 'الرياض - حي الغدير',
                    ],
                    [
                        'name' => 'سعد المطيري',
                        'phone_number' => '0599200003',
                        'nationality' => 'Saudi Arabia',
                        'id_number' => '1099200003',
                        'gender' => 'male',
                        'address' => 'الرياض - حي الوادي',
                    ],
                ],
            ],
            [
                'name_ar' => 'فريق الدعم الفني والتنظيف 2',
                'name_en' => 'Specialized Cleaning Team 2',
                'code' => 'TEAM-02',
                'status' => 'active',
                'notes' => 'فريق متخصص لغسيل المكيفات والأسطح والسجاد',
                'workers' => [
                    [
                        'name' => 'عمر الشهري',
                        'phone_number' => '0599200004',
                        'nationality' => 'Saudi Arabia',
                        'id_number' => '1099200004',
                        'gender' => 'male',
                        'address' => 'الرياض - حي الملقا',
                    ],
                    [
                        'name' => 'بدر الحربي',
                        'phone_number' => '0599200005',
                        'nationality' => 'Saudi Arabia',
                        'id_number' => '1099200005',
                        'gender' => 'male',
                        'address' => 'الرياض - حي حطين',
                    ],
                    [
                        'name' => 'تركي الشمري',
                        'phone_number' => '0599200006',
                        'nationality' => 'Saudi Arabia',
                        'id_number' => '1099200006',
                        'gender' => 'male',
                        'address' => 'الرياض - حي الياسمين',
                    ],
                ],
            ],
        ];

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

            // Create/assign 3 male workers
            $workerIds = [];
            foreach ($tData['workers'] as $wData) {
                $worker = Worker::firstOrCreate(
                    ['phone_number' => $wData['phone_number']],
                    [
                        'name' => $wData['name'],
                        'nationality' => $wData['nationality'],
                        'id_number' => $wData['id_number'],
                        'gender' => 'male',
                        'address' => $wData['address'],
                        'status' => 'active',
                        'team_id' => $team->id,
                    ]
                );

                $worker->update(['team_id' => $team->id, 'gender' => 'male']);
                $workerIds[] = $worker->id;
            }

            // Bind team to specialized services (6 to 11)
            $serviceIds = $specializedServices->pluck('id')->toArray();
            $team->services()->syncWithoutDetaching($serviceIds);

            // Bind team workers to all these services (multi-service assignment for 6 to 11)
            foreach ($specializedServices as $srv) {
                $srv->workers()->syncWithoutDetaching($workerIds);
            }

            $this->command->info("Seeded Team: {$team->name_ar} ({$team->code}) with 3 male workers and linked to " . count($serviceIds) . " services.");
        }
    }
}
