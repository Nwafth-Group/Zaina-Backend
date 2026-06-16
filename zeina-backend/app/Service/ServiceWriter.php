<?php

namespace App\Service;

use App\Models\Area;
use App\Models\Service;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Owns the transactional persistence of a Service and all of its related
 * configuration (durations, rules, coverages, prices, worker links, icon),
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
        'category_id', 'type', 'name_ar', 'name_en',
        'description_ar', 'description_en', 'status', 'features',
    ];

    /**
     * Relations eager-loaded onto the returned service for the API response.
     *
     * @var list<string>
     */
    private const RESPONSE_RELATIONS = [
        'category', 'duration', 'rules', 'coverages', 'coverages.city', 'coverages.area',
        'priceRules', 'priceRules.city', 'priceRules.area', 'workers', 'media', 'schedules',
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
     * Upsert durations/rules, replace coverages/prices, and sync workers. Each
     * section is only touched when present in the payload (so partial updates
     * leave the rest intact). For a brand-new service the deletes are no-ops.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncRelations(Service $service, array $data): void
    {
        if (array_key_exists('durations', $data)) {
            $service->duration()->updateOrCreate(['service_id' => $service->id], $data['durations']);
        }

        if (array_key_exists('rules', $data)) {
            $service->rules()->updateOrCreate(['service_id' => $service->id], $data['rules']);
        }

        if (array_key_exists('coverages', $data)) {
            $service->coverages()->delete();
            $service->coverages()->createMany($data['coverages']);
        }

        if (array_key_exists('prices', $data)) {
            $service->priceRules()->delete();
            $service->priceRules()->createMany($this->normalizePrices($data['prices']));
        }

        if (array_key_exists('workers', $data)) {
            $service->workers()->sync($data['workers']);
        }

        if (array_key_exists('schedules', $data)) {
            $service->schedules()->delete();
            $service->schedules()->createMany($data['schedules']);
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

    /**
     * Normalize price rows to the real DB columns: drop the transient
     * `pricing_scope` hint and derive the required `city_id` from the area so it
     * can never be tampered with from the client.
     *
     * @param  array<int, array<string, mixed>>  $prices
     * @return array<int, array<string, mixed>>
     */
    private function normalizePrices(array $prices): array
    {
        return array_map(function (array $price): array {
            unset($price['pricing_scope']);

            if (! empty($price['area_id'])) {
                $price['city_id'] = Area::find($price['area_id'])?->city_id;
            }

            return $price;
        }, $prices);
    }
}
