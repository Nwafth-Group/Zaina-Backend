<?php

namespace App\Service;

use App\Models\Service;
use App\Models\Team;
use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the transactional persistence of a Service and all of its related
 * configuration (durations, rules, service zones, worker links, icon),
 * keeping the controller thin. Create and update share the same relation-sync
 * path; only base-attribute assignment and the returned instance differ.
 */
class ServiceWriter
{
    /**
     * Base service columns that may be mass-assigned from the payload.
     *
     * @var list<string>
     */
    private const BASE_FIELDS = [
        'category_id', 'type', 'worker_gender_options', 'pricing_type', 'is_team_based', 'name_ar', 'name_en',
        'description_ar', 'description_en', 'status', 'features',
        'service_code', 'available_visit_types', 'terms_ar',
        'terms_en', 'start_time', 'end_time',
        'default_duration', 'has_duration_range',
        'min_duration', 'max_duration', 'min_workers', 'max_workers', 'base_price',
        'min_children', 'max_children', 'covered_persons_per_worker',
        'points_redemption_enabled', 'points_redemption_method', 'points_required_fixed', 'points_conversion_rate',
    ];

    /**
     * Relations eager-loaded onto the returned service for the API response.
     *
     * @var list<string>
     */
    private const RESPONSE_RELATIONS = [
        'category', 'rules', 'workers', 'teams', 'media', 'schedules',
        'childAgeGroups', 'serviceZones', 'options',
    ];

    public function __construct(private MediaService $mediaService) {}

    /**
     * Create a service together with all of its related configuration.
     *
     * @param  array<string, mixed>  $data  Validated service payload.
     */
    public function create(array $data, ?UploadedFile $image = null): Service
    {
        return DB::transaction(function () use ($data, $image): Service {
            $service = Service::create($this->baseAttributes($data));

            $this->syncRelations($service, $data);
            $this->uploadIcon($service, $image, replaceExisting: false);

            return $service->load(self::RESPONSE_RELATIONS);
        });
    }

    /**
     * Update a service and replace its related configuration. Sections absent
     * from the payload are left untouched.
     *
     * @param  array<string, mixed>  $data  Validated service payload (partial allowed).
     */
    public function update(Service $service, array $data, ?UploadedFile $image = null): Service
    {
        return DB::transaction(function () use ($service, $data, $image): Service {
            $service->update($this->baseAttributes($data));

            $this->syncRelations($service, $data);
            $this->uploadIcon($service, $image, replaceExisting: true);

            return $service->fresh(self::RESPONSE_RELATIONS);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function baseAttributes(array $data): array
    {
        return array_intersect_key($data, array_flip(self::BASE_FIELDS));
    }

    /**
     * Upsert rules, sync service zones, and sync workers. Each
     * section is only touched when present in the payload (so partial updates
     * leave the rest intact). For a brand-new service the deletes are no-ops.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncRelations(Service $service, array $data): void
    {
        if (array_key_exists('rules', $data)) {
            $service->rules()->updateOrCreate(['service_id' => $service->id], $data['rules']);
        }

        $isTeamBased = $service->isTeamBased();

        if ($isTeamBased) {
            if (array_key_exists('teams', $data)) {
                $teamIds = is_array($data['teams']) ? array_values(array_filter($data['teams'])) : [];
                $service->teams()->sync($teamIds);

                // Automatically sync workers belonging to these teams
                if (!empty($teamIds)) {
                    $teamWorkerIds = Worker::whereIn('team_id', $teamIds)->pluck('id')->toArray();
                    $service->workers()->sync($teamWorkerIds);
                } else {
                    $service->workers()->sync([]);
                }
            }
        } else {
            // Worker-based service: clear teams to prevent inconsistency
            $service->teams()->sync([]);

            if (array_key_exists('workers', $data)) {
                $workerIds = is_array($data['workers']) ? array_values(array_filter($data['workers'])) : [];
                if (! empty($workerIds)) {
                    $isSpecialized = in_array(strtoupper($service->service_code ?? ''), array_map('strtoupper', Service::getTeamBasedCodes()), true);

                    if ($isSpecialized) {
                        // For specialized services (6 to 11), workers cannot be assigned to standard services (1 to 5)
                        $assignedToStandard = DB::table('service_workers')
                            ->join('services', 'services.id', '=', 'service_workers.service_id')
                            ->whereIn('service_workers.worker_id', $workerIds)
                            ->whereNotIn('services.service_code', Service::getTeamBasedCodes())
                            ->exists();

                        if ($assignedToStandard) {
                            throw ValidationException::withMessages([
                                'workers' => 'أحد العمال محجوز في خدمات بالساعة (1-5)، ولا يمكن إسناده لخدمات تخصصية.',
                            ]);
                        }
                    } else {
                        // For standard services (1 to 5), worker can only be assigned to one service
                        $alreadyAssigned = DB::table('service_workers')
                            ->whereIn('worker_id', $workerIds)
                            ->where('service_id', '!=', $service->id)
                            ->exists();

                        if ($alreadyAssigned) {
                            throw ValidationException::withMessages([
                                'workers' => 'هذا العامل مسند لخدمة أخرى بالفعل. العامل في الخدمات العادية يسند لخدمة واحدة فقط.',
                            ]);
                        }
                    }
                }
                $service->workers()->sync($workerIds);
            }
        }

        if (array_key_exists('service_zone_ids', $data)) {
            $zoneIds = is_array($data['service_zone_ids']) ? array_values(array_filter($data['service_zone_ids'])) : [];
            $service->serviceZones()->sync($zoneIds);
        }

        if (array_key_exists('working_days', $data) || array_key_exists('shifts', $data)) {
            $workingDays = array_key_exists('working_days', $data)
                ? $data['working_days']
                : $service->schedules()->pluck('day')->unique()->toArray();

            $shifts = array_key_exists('shifts', $data)
                ? $data['shifts']
                : $service->schedules()->get()->map(function ($s) {
                    return [
                        'start_time' => Carbon::parse($s->start_time)->format('H:i'),
                        'end_time' => $s->end_time ? Carbon::parse($s->end_time)->format('H:i') : null,
                    ];
                })->unique('start_time')->values()->toArray();

            if (empty($shifts)) {
                $startTime = $data['start_time'] ?? $service->start_time ?? '08:00';
                $endTime = $data['end_time'] ?? $service->end_time ?? '20:00';
                // Trim seconds if present
                $startTime = substr($startTime, 0, 5);
                $endTime = substr($endTime, 0, 5);
                $shifts = [
                    ['start_time' => $startTime, 'end_time' => $endTime],
                ];
            }

            $service->schedules()->delete();
            $schedulesToCreate = [];
            $defaultDuration = (int) ($data['default_duration'] ?? $service->default_duration ?? 1);

            foreach ($workingDays as $day) {
                foreach ($shifts as $shift) {
                    if (empty($shift['start_time'])) {
                        continue;
                    }
                    $start = Carbon::parse($shift['start_time']);

                    $end = ! empty($shift['end_time'])
                        ? Carbon::parse($shift['end_time'])
                        : $start->copy()->addHours($defaultDuration);

                    $schedulesToCreate[] = [
                        'day' => $day,
                        'start_time' => $start->format('H:i:s'),
                        'end_time' => $end->format('H:i:s'),
                        'is_active' => true,
                    ];
                }
            }
            $service->schedules()->createMany($schedulesToCreate);
        } elseif (array_key_exists('schedules', $data)) {
            $service->schedules()->delete();
            $service->schedules()->createMany($data['schedules']);
        }

        if (array_key_exists('child_age_groups', $data)) {
            $service->childAgeGroups()->delete();
            if (! empty($data['child_age_groups'])) {
                $service->childAgeGroups()->createMany($data['child_age_groups']);
            }
        }

        if (array_key_exists('options', $data)) {
            $service->options()->delete();
            if (! empty($data['options'])) {
                $service->options()->createMany($data['options']);
            }
        }
    }

    private function uploadIcon(Service $service, ?UploadedFile $image, bool $replaceExisting): void
    {
        if (! $image) {
            return;
        }

        if ($replaceExisting) {
            $existing = $service->media()->where('collection', 'service/icon')->first();
            if ($existing) {
                $this->mediaService->delete($existing);
            }
        }

        $this->mediaService->upload($image, $service, 'service/icon', 'public');
    }
}
