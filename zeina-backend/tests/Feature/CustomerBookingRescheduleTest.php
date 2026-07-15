<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\BookingVisit;
use App\Models\Service;
use App\Models\ServiceRule;
use App\Models\ServiceSchedule;
use App\Models\User;
use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class CustomerBookingRescheduleTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    protected function actingAsCustomer(array $overrides = []): User
    {
        $customer = User::factory()->customer()->create(array_merge([
            'status' => 'active',
            'verification_status' => 'approved',
        ], $overrides));

        $this->actingAs($customer, 'sanctum');

        return $customer;
    }

    public function test_reschedule_single_visit_keeps_same_workers_if_available(): void
    {
        $user = $this->actingAsCustomer();
        $service = Service::factory()->create(['status' => 'active']);

        $rules = ServiceRule::factory()->create([
            'service_id' => $service->id,
            'allow_reschedule' => true,
            'reschedule_cutoff_hours' => 24,
        ]);

        $booking = Booking::factory()->create([
            'customer_id' => $user->id,
            'service_id' => $service->id,
            'status' => 'Upcoming',
            'booking_date' => Carbon::now()->addDays(3)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 120,
            'visit_type' => 'single_visit',
        ]);

        $worker = Worker::factory()->create(['status' => 'active']);
        $service->workers()->attach($worker->id);

        BookingAssignment::create([
            'booking_id' => $booking->id,
            'worker_id' => $worker->id,
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $newDateStr = Carbon::now()->addDays(5)->toDateString();
        $dayName = strtolower(Carbon::parse($newDateStr)->format('l'));

        ServiceSchedule::create([
            'service_id' => $service->id,
            'day' => $dayName,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
        ]);

        $this->postJson("/api/v1/customer/bookings/{$booking->id}/reschedule/send-otp", [
            'dates' => [$newDateStr],
            'start_time' => '10:00',
        ])->assertOk();

        $response = $this->postJson("/api/v1/customer/bookings/{$booking->id}/reschedule", [
            'dates' => [$newDateStr],
            'start_time' => '10:00',
            'otp' => '123456',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.booking_status', 'Upcoming');

        $booking->refresh();
        $this->assertEquals($newDateStr, $booking->booking_date);
        $this->assertEquals('10:00:00', $booking->start_time);

        $this->assertDatabaseHas('booking_assignments', [
            'booking_id' => $booking->id,
            'worker_id' => $worker->id,
            'status' => 'active',
        ]);
    }

    public function test_reschedule_single_visit_reassigns_workers_if_busy(): void
    {
        $user = $this->actingAsCustomer();
        $service = Service::factory()->create(['status' => 'active']);

        $rules = ServiceRule::factory()->create([
            'service_id' => $service->id,
            'allow_reschedule' => true,
            'reschedule_cutoff_hours' => 24,
        ]);

        $booking = Booking::factory()->create([
            'customer_id' => $user->id,
            'service_id' => $service->id,
            'status' => 'Upcoming',
            'booking_date' => Carbon::now()->addDays(3)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 120,
            'visit_type' => 'single_visit',
        ]);

        $worker1 = Worker::factory()->create(['status' => 'active']);
        $service->workers()->attach($worker1->id);

        BookingAssignment::create([
            'booking_id' => $booking->id,
            'worker_id' => $worker1->id,
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $worker2 = Worker::factory()->create(['status' => 'active']);
        $service->workers()->attach($worker2->id);

        $newDateStr = Carbon::now()->addDays(5)->toDateString();
        $dayName = strtolower(Carbon::parse($newDateStr)->format('l'));

        ServiceSchedule::create([
            'service_id' => $service->id,
            'day' => $dayName,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
        ]);

        $otherBooking = Booking::factory()->create([
            'status' => 'Upcoming',
            'booking_date' => $newDateStr,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
        ]);
        BookingAssignment::create([
            'booking_id' => $otherBooking->id,
            'worker_id' => $worker1->id,
            'status' => 'active',
        ]);

        $this->postJson("/api/v1/customer/bookings/{$booking->id}/reschedule/send-otp", [
            'dates' => [$newDateStr],
            'start_time' => '10:00',
        ])->assertOk();

        $response = $this->postJson("/api/v1/customer/bookings/{$booking->id}/reschedule", [
            'dates' => [$newDateStr],
            'start_time' => '10:00',
            'otp' => '123456',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('booking_assignments', [
            'booking_id' => $booking->id,
            'worker_id' => $worker1->id,
            'status' => 'replaced',
        ]);

        $this->assertDatabaseHas('booking_assignments', [
            'booking_id' => $booking->id,
            'worker_id' => $worker2->id,
            'status' => 'active',
        ]);
    }

    public function test_reschedule_single_visit_blocks_if_no_workers_available(): void
    {
        $user = $this->actingAsCustomer();
        $service = Service::factory()->create(['status' => 'active']);

        $rules = ServiceRule::factory()->create([
            'service_id' => $service->id,
            'allow_reschedule' => true,
            'reschedule_cutoff_hours' => 24,
        ]);

        $booking = Booking::factory()->create([
            'customer_id' => $user->id,
            'service_id' => $service->id,
            'status' => 'Upcoming',
            'booking_date' => Carbon::now()->addDays(3)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 120,
            'visit_type' => 'single_visit',
        ]);

        $worker1 = Worker::factory()->create(['status' => 'active']);
        $service->workers()->attach($worker1->id);

        BookingAssignment::create([
            'booking_id' => $booking->id,
            'worker_id' => $worker1->id,
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $newDateStr = Carbon::now()->addDays(5)->toDateString();
        $dayName = strtolower(Carbon::parse($newDateStr)->format('l'));

        ServiceSchedule::create([
            'service_id' => $service->id,
            'day' => $dayName,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
        ]);

        $otherBooking = Booking::factory()->create([
            'status' => 'Upcoming',
            'booking_date' => $newDateStr,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
        ]);
        BookingAssignment::create([
            'booking_id' => $otherBooking->id,
            'worker_id' => $worker1->id,
            'status' => 'active',
        ]);

        $response = $this->postJson("/api/v1/customer/bookings/{$booking->id}/reschedule/send-otp", [
            'dates' => [$newDateStr],
            'start_time' => '10:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', __('messages.worker_not_available') ?? 'Cannot reschedule: the assigned workers are not available during the selected times.');
    }

    public function test_reschedule_3_visits_success_same_week(): void
    {
        $user = $this->actingAsCustomer();
        $service = Service::factory()->create(['status' => 'active']);

        $rules = ServiceRule::factory()->create([
            'service_id' => $service->id,
            'allow_reschedule' => true,
            'reschedule_cutoff_hours' => 24,
        ]);

        $booking = Booking::factory()->create([
            'customer_id' => $user->id,
            'service_id' => $service->id,
            'status' => 'Upcoming',
            'booking_date' => Carbon::now()->addDays(3)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 120,
            'visit_type' => 'three_visits_per_week',
        ]);

        $worker = Worker::factory()->create(['status' => 'active']);
        $service->workers()->attach($worker->id);

        BookingAssignment::create([
            'booking_id' => $booking->id,
            'worker_id' => $worker->id,
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $visit1 = BookingVisit::create([
            'booking_id' => $booking->id,
            'visit_date' => Carbon::now()->addDays(3)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'status' => 'Upcoming',
        ]);
        $visit2 = BookingVisit::create([
            'booking_id' => $booking->id,
            'visit_date' => Carbon::now()->addDays(4)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'status' => 'Upcoming',
        ]);
        $visit3 = BookingVisit::create([
            'booking_id' => $booking->id,
            'visit_date' => Carbon::now()->addDays(5)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'status' => 'Upcoming',
        ]);

        $nextMonday = Carbon::now()->next(Carbon::MONDAY);
        $date1 = $nextMonday->toDateString();
        $date2 = $nextMonday->copy()->addDays(2)->toDateString();
        $date3 = $nextMonday->copy()->addDays(4)->toDateString();

        foreach ([$date1, $date2, $date3] as $d) {
            $dayName = strtolower(Carbon::parse($d)->format('l'));
            ServiceSchedule::create([
                'service_id' => $service->id,
                'day' => $dayName,
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'is_active' => true,
            ]);
        }

        $this->postJson("/api/v1/customer/bookings/{$booking->id}/reschedule/send-otp", [
            'dates' => [$date1, $date2, $date3],
            'start_time' => '10:00',
        ])->assertOk();

        $response = $this->postJson("/api/v1/customer/bookings/{$booking->id}/reschedule", [
            'dates' => [$date1, $date2, $date3],
            'start_time' => '10:00',
            'otp' => '123456',
        ]);

        $response->assertOk();

        $booking->refresh();
        $this->assertEquals($date1, $booking->booking_date);

        $this->assertDatabaseHas('booking_visits', [
            'id' => $visit1->id,
            'visit_date' => $date1,
            'start_time' => '10:00:00',
        ]);

        $this->assertDatabaseHas('booking_visits', [
            'id' => $visit2->id,
            'visit_date' => $date2,
            'start_time' => '10:00:00',
        ]);

        $this->assertDatabaseHas('booking_visits', [
            'id' => $visit3->id,
            'visit_date' => $date3,
            'start_time' => '10:00:00',
        ]);
    }

    public function test_reschedule_3_visits_fails_different_weeks(): void
    {
        $user = $this->actingAsCustomer();
        $service = Service::factory()->create(['status' => 'active']);

        $rules = ServiceRule::factory()->create([
            'service_id' => $service->id,
            'allow_reschedule' => true,
            'reschedule_cutoff_hours' => 24,
        ]);

        $booking = Booking::factory()->create([
            'customer_id' => $user->id,
            'service_id' => $service->id,
            'status' => 'Upcoming',
            'booking_date' => Carbon::now()->addDays(3)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 120,
            'visit_type' => 'three_visits_per_week',
        ]);

        $date1 = Carbon::now()->next(Carbon::MONDAY)->toDateString();
        $date2 = Carbon::now()->next(Carbon::WEDNESDAY)->toDateString();
        $date3 = Carbon::now()->next(Carbon::MONDAY)->addDays(7)->toDateString();

        $response = $this->postJson("/api/v1/customer/bookings/{$booking->id}/reschedule/send-otp", [
            'dates' => [$date1, $date2, $date3],
            'start_time' => '10:00',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('message', 'All selected dates must belong to the same calendar week.');
    }

    public function test_reschedule_fails_if_any_visit_completed(): void
    {
        $user = $this->actingAsCustomer();
        $service = Service::factory()->create(['status' => 'active']);

        $rules = ServiceRule::factory()->create([
            'service_id' => $service->id,
            'allow_reschedule' => true,
            'reschedule_cutoff_hours' => 24,
        ]);

        $booking = Booking::factory()->create([
            'customer_id' => $user->id,
            'service_id' => $service->id,
            'status' => 'Upcoming',
            'booking_date' => Carbon::now()->addDays(3)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 120,
            'visit_type' => 'three_visits_per_week',
        ]);

        BookingVisit::create([
            'booking_id' => $booking->id,
            'visit_date' => Carbon::now()->addDays(3)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'status' => 'completed',
        ]);

        $date1 = Carbon::now()->addDays(5)->toDateString();
        $date2 = Carbon::now()->addDays(6)->toDateString();
        $date3 = Carbon::now()->addDays(7)->toDateString();

        $response = $this->postJson("/api/v1/customer/bookings/{$booking->id}/reschedule/send-otp", [
            'dates' => [$date1, $date2, $date3],
            'start_time' => '10:00',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Cannot reschedule a booking that has already started or been completed.');
    }

    public function test_reschedule_fails_if_within_cutoff_hours(): void
    {
        $user = $this->actingAsCustomer();
        $service = Service::factory()->create(['status' => 'active']);

        $rules = ServiceRule::factory()->create([
            'service_id' => $service->id,
            'allow_reschedule' => true,
            'reschedule_cutoff_hours' => 24,
        ]);

        $booking = Booking::factory()->create([
            'customer_id' => $user->id,
            'service_id' => $service->id,
            'status' => 'Upcoming',
            'booking_date' => Carbon::now()->addHours(10)->toDateString(),
            'start_time' => Carbon::now()->addHours(10)->format('H:i:s'),
            'end_time' => Carbon::now()->addHours(12)->format('H:i:s'),
            'duration_minutes' => 120,
            'visit_type' => 'single_visit',
        ]);

        $newDateStr = Carbon::now()->addDays(5)->toDateString();

        $response = $this->postJson("/api/v1/customer/bookings/{$booking->id}/reschedule/send-otp", [
            'dates' => [$newDateStr],
            'start_time' => '10:00',
        ]);

        $response->assertStatus(400);
    }

    public function test_reschedule_fails_if_disabled_by_rules(): void
    {
        $user = $this->actingAsCustomer();
        $service = Service::factory()->create(['status' => 'active']);

        $rules = ServiceRule::factory()->create([
            'service_id' => $service->id,
            'allow_reschedule' => false,
            'reschedule_cutoff_hours' => 24,
        ]);

        $booking = Booking::factory()->create([
            'customer_id' => $user->id,
            'service_id' => $service->id,
            'status' => 'Upcoming',
            'booking_date' => Carbon::now()->addDays(3)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 120,
            'visit_type' => 'single_visit',
        ]);

        $newDateStr = Carbon::now()->addDays(5)->toDateString();

        $response = $this->postJson("/api/v1/customer/bookings/{$booking->id}/reschedule/send-otp", [
            'dates' => [$newDateStr],
            'start_time' => '10:00',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Rescheduling is not allowed for this service.');
    }

    public function test_cannot_reschedule_with_invalid_otp(): void
    {
        $user = $this->actingAsCustomer();
        $service = Service::factory()->create(['status' => 'active']);
        ServiceRule::factory()->create([
            'service_id' => $service->id,
            'allow_reschedule' => true,
        ]);
        $booking = Booking::factory()->create([
            'customer_id' => $user->id,
            'service_id' => $service->id,
            'status' => 'Upcoming',
            'booking_date' => Carbon::now()->addDays(3)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 120,
            'visit_type' => 'single_visit',
        ]);

        $worker = Worker::factory()->create(['status' => 'active']);
        $service->workers()->attach($worker->id);
        BookingAssignment::create([
            'booking_id' => $booking->id,
            'worker_id' => $worker->id,
            'status' => 'active',
        ]);

        $newDateStr = Carbon::now()->addDays(5)->toDateString();
        ServiceSchedule::create([
            'service_id' => $service->id,
            'day' => strtolower(Carbon::parse($newDateStr)->format('l')),
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
        ]);

        $this->postJson("/api/v1/customer/bookings/{$booking->id}/reschedule/send-otp", [
            'dates' => [$newDateStr],
            'start_time' => '10:00',
        ])->assertOk();

        $response = $this->postJson("/api/v1/customer/bookings/{$booking->id}/reschedule", [
            'dates' => [$newDateStr],
            'start_time' => '10:00',
            'otp' => '999999', // wrong OTP
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_reschedule_direct_without_otp(): void
    {
        $user = $this->actingAsCustomer();
        $service = Service::factory()->create(['status' => 'active']);
        ServiceRule::factory()->create([
            'service_id' => $service->id,
            'allow_reschedule' => false, // disabled for customer, admin bypasses
        ]);
        $booking = Booking::factory()->create([
            'customer_id' => $user->id,
            'service_id' => $service->id,
            'status' => 'Upcoming',
            'booking_date' => Carbon::now()->addDays(3)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 120,
            'visit_type' => 'single_visit',
        ]);

        $worker = Worker::factory()->create(['status' => 'active']);
        $service->workers()->attach($worker->id);
        BookingAssignment::create([
            'booking_id' => $booking->id,
            'worker_id' => $worker->id,
            'status' => 'active',
        ]);

        $newDateStr = Carbon::now()->addDays(5)->toDateString();
        ServiceSchedule::create([
            'service_id' => $service->id,
            'day' => strtolower(Carbon::parse($newDateStr)->format('l')),
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
        ]);

        $admin = $this->actingAsAdmin();

        // Direct POST to admin reschedule endpoint without OTP
        $response = $this->postJson("/api/v1/admin/bookings/{$booking->id}/reschedule", [
            'dates' => [$newDateStr],
            'start_time' => '10:00',
        ]);

        $response->assertOk();
        $booking->refresh();
        $this->assertEquals($newDateStr, $booking->booking_date);
    }
}
