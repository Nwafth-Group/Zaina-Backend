<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\Service;
use App\Models\Worker;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class WorkerStoryTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/workers';

    public function test_cannot_create_worker_with_missing_mandatory_data(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::ENDPOINT, [
            'name' => '', // missing name
            'phone_number' => '',
            'nationality' => 'Egypt',
            'id_number' => '',
            'address' => '',
            'gender' => '',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['name', 'phone_number', 'id_number', 'address', 'gender']]);
    }

    public function test_cannot_create_worker_with_duplicate_phone_or_id(): void
    {
        $this->actingAsAdmin();

        Worker::factory()->create([
            'phone_number' => '0500000001',
            'id_number' => '1111111111',
        ]);

        // Duplicate phone number
        $this->postJson(self::ENDPOINT, [
            'name' => 'Worker A',
            'phone_number' => '0500000001',
            'nationality' => 'Egypt',
            'id_number' => '2222222222',
            'address' => 'Cairo',
            'gender' => 'female',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['phone_number']]);

        // Duplicate ID number
        $this->postJson(self::ENDPOINT, [
            'name' => 'Worker B',
            'phone_number' => '0500000002',
            'nationality' => 'Egypt',
            'id_number' => '1111111111',
            'address' => 'Cairo',
            'gender' => 'female',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['id_number']]);
    }

    public function test_worker_can_only_be_assigned_to_one_service(): void
    {
        $this->actingAsAdmin();
        $services = Service::factory()->count(2)->create();

        // Creating worker with more than 1 service fails validation
        $this->postJson(self::ENDPOINT, [
            'name' => 'Layla Hassan',
            'phone_number' => '0511111111',
            'nationality' => 'Egypt',
            'id_number' => '1234567890',
            'address' => 'Cairo, Egypt',
            'gender' => 'female',
            'services' => $services->pluck('id')->toArray(),
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['services']]);
    }

    public function test_service_writer_blocks_assigning_already_linked_worker(): void
    {
        $this->actingAsAdmin();
        $worker = Worker::factory()->create();
        [$serviceA, $serviceB] = Service::factory()->count(2)->create()->all();

        $worker->services()->attach($serviceA->id);

        // Try to update Service B to include this worker -> must fail validation
        $this->putJson("/api/v1/admin/services/{$serviceB->id}", [
            'name_ar' => 'Test Service B',
            'name_en' => 'Test Service B',
            'workers' => [$worker->id],
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['workers']]);
    }

    public function test_unassigned_only_filters_workers_correctly(): void
    {
        $this->actingAsAdmin();
        $service = Service::factory()->create();
        $workerAssigned = Worker::factory()->create();
        $workerAssigned->services()->attach($service->id);
        $workerUnassigned = Worker::factory()->create();

        // 1. Get only unassigned workers
        $this->getJson(self::ENDPOINT . '?unassigned_only=true')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $workerUnassigned->id);

        // 2. Get unassigned workers plus workers from specific service
        $this->getJson(self::ENDPOINT . "?unassigned_only=true&include_service_id={$service->id}")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.items');
    }
}
