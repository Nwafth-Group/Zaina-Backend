<?php

namespace App\Service;

use App\Models\Area;
use App\Models\Service;
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
        'category_id', 'type', 'worker_gender_options', 'pricing_type', 'name_ar', 'name_en',
        'description_ar', 'description_en', 'status', 'features',
        'service_code', 'available_visit_types', 'discount_value', 'terms_ar',
        'terms_en', 'acceptance_required', 'start_time', 'end_time',
        'default_duration', 'duration_unit', 'has_duration_range',
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
        'category', 'duration', 'rules', 'workers', 'media', 'schedules',
        'childAgeGroups', 'serviceZones',
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
        $attributes = array_intersect_key($data, array_flip(self::BASE_FIELDS));
        $attributes['acceptance_required'] = true;

        return $attributes;
    }

    /**
     * Upsert durations/rules, sync service zones, and sync workers. Each
     * section is only touched when present in the payload (so partial updates
     * leave the rest intact). For a brand-new service the deletes are no-ops.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncRelations(Service $service, array $data): void
    {
        // For backward compatibility, keep the duration relationship updated
        $durationData = [];
        if (isset($data['default_duration'])) {
            $durationData['service_period_hours'] = $data['default_duration'];
        } elseif (isset($data['durations']['service_period_hours'])) {
            $durationData['service_period_hours'] = $data['durations']['service_period_hours'];
        }

        if (isset($data['durations']['allowed_contract_durations'])) {
            $durationData['allowed_contract_durations'] = $data['durations']['allowed_contract_durations'];
        }
        if (isset($data['durations']['min_contract_months'])) {
            $durationData['min_contract_months'] = $data['durations']['min_contract_months'];
        }

        if (! empty($durationData)) {
            $service->duration()->updateOrCreate(['service_id' => $service->id], $durationData);
        } elseif (array_key_exists('durations', $data)) {
            $service->duration()->updateOrCreate(['service_id' => $service->id], $data['durations']);
        }

        if (array_key_exists('rules', $data)) {
            $service->rules()->updateOrCreate(['service_id' => $service->id], $data['rules']);
        }

        if (array_key_exists('workers', $data)) {
            $workerIds = $data['workers'] ?? [];
            if (! empty($workerIds)) {
                $alreadyAssigned = DB::table('service_workers')
                    ->whereIn('worker_id', $workerIds)
                    ->where('service_id', '!=', $service->id)
                    ->exists();

                if ($alreadyAssigned) {
                    throw ValidationException::withMessages([
                        'workers' => 'This worker is already assigned to a service. A worker can only be assigned to one service.',
                    ]);
                }
            }
            $service->workers()->sync($workerIds);
        }

        if (array_key_exists('service_zone_ids', $data)) {
            $service->serviceZones()->sync($data['service_zone_ids'] ?? []);
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
