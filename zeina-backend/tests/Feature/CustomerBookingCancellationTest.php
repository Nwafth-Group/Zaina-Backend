<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\BookingVisit;
use App\Models\City;
use App\Models\CustomerAddress;
use App\Models\Service;
use App\Models\ServiceRule;
use App\Models\User;
use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class CustomerBookingCancellationTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsAdmin;

    protected function actingAsCustomer(array $overrides = []): User
    {
        $customer = User::factory()->customer()->create(array_merge([
            'status' => 'active',
            'verification_status' => 'approved',
        ], $overrides));

        $this->actingAs($customer, 'sanctum');

        return $customer;
    }

    public function test_cancellation_reason_is_required(): void
    {
        $user = $this->actingAsCustomer();
        $booking = Booking::factory()->create([
            'customer_id' => $user->id,
            'status' => 'Upcoming',
        ]);

        $response = $this->putJson("/api/v1/customer/bookings/{$booking->id}/cancel", []);
        $response->assertStatus(422)
            ->assertJsonPath('params.cancellation_reason', 'The cancellation reason field is required.')
            ->assertJsonPath('params.otp', 'The otp field is required.');
    }

    public function test_single_visit_successful_cancellation_and_wallet_refund(): void
    {
        $user = $this->actingAsCustomer();
        $user->deposit(100.00, 'Initial Deposit');

        $service = Service::factory()->create(['status' => 'active', 'base_price' => 100.00]);
        $rules = ServiceRule::factory()->create([
            'service_id' => $service->id,
            'allow_cancel' => true,
            'free_cancel_cutoff_hours' => 24,
        ]);

        $worker = Worker::factory()->create(['status' => 'active']);
        $address = CustomerAddress::factory()->create(['user_id' => $user->id]);

        $booking = Booking::create([
            'booking_ref' => 'BK-12345',
            'customer_id' => $user->id,
            'service_id' => $service->id,
            'address_id' => $address->id,
            'booking_date' => Carbon::now()->addDays(2)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'duration_minutes' => 120,
            'price_subtotal' => 100.00,
            'total_amount' => 100.00,
            'status' => 'Upcoming',
            'payment_status' => 'paid',
            'payment_method' => 'wallet',
            'visit_type' => 'single_visit',
        ]);

        $visit = BookingVisit::create([
            'booking_id' => $booking->id,
            'visit_date' => $booking->booking_date,
            'start_time' => $booking->start_time,
            'end_time' => $booking->end_time,
            'status' => 'Upcoming',
        ]);

        BookingAssignment::create([
            'booking_id' => $booking->id,
            'worker_id' => $worker->id,
            'status' => 'active',
        ]);

        // Simulate booking payment deduction
        $user->withdraw(100.00, 'Payment deduction simulation');
        $user->refresh();
        $this->assertEquals(0, $user->wallet_balance); 

        $this->postJson("/api/v1/customer/bookings/{$booking->id}/cancel/send-otp")->assertOk();

        $response = $this->putJson("/api/v1/customer/bookings/{$booking->id}/cancel", [
            'cancellation_reason' => 'Change of plans',
            'otp' => '123456',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.booking_status', 'cancelled')
            ->assertJsonPath('data.refund_status', 'completed')
            ->assertJsonPath('data.refund_destination', 'wallet')
            ->assertJsonPath('data.payment_method', 'wallet')
            ->assertJsonPath('data.cancellation_reason', 'Change of plans');

        $this->assertEquals(100.00, $response->json('data.refund_amount'));

        // Check wallet balance is refunded
        $user->refresh();
        $this->assertEquals(100.00, $user->wallet_balance);

        // Check assignment remains active so workers are not removed from detail resource
        $this->assertDatabaseHas('booking_assignments', [
            'booking_id' => $booking->id,
            'worker_id' => $worker->id,
            'status' => 'active',
        ]);

        // Check visit status is cancelled
        $this->assertDatabaseHas('booking_visits', [
            'id' => $visit->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_3_visits_successful_cancellation_and_points_refund(): void
    {
        $user = $this->actingAsCustomer();
        $service = Service::factory()->create(['status' => 'active', 'base_price' => 100.00]);
        ServiceRule::factory()->create([
            'service_id' => $service->id,
            'allow_cancel' => true,
            'free_cancel_cutoff_hours' => 24,
        ]);
        $address = CustomerAddress::factory()->create(['user_id' => $user->id]);

        $booking = Booking::create([
            'booking_ref' => 'BK-67890',
            'customer_id' => $user->id,
            'service_id' => $service->id,
            'address_id' => $address->id,
            'booking_date' => Carbon::now()->addDays(3)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'duration_minutes' => 120,
            'price_subtotal' => 300.00,
            'total_amount' => 300.00,
            'status' => 'Upcoming',
            'payment_status' => 'paid',
            'payment_method' => 'points',
            'paid_points' => 300,
            'visit_type' => 'three_visits_per_week',
        ]);

        $visit1 = BookingVisit::create([
            'booking_id' => $booking->id,
            'visit_date' => $booking->booking_date,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'status' => 'Upcoming',
        ]);
        $visit2 = BookingVisit::create([
            'booking_id' => $booking->id,
            'visit_date' => Carbon::now()->addDays(5)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'status' => 'Upcoming',
        ]);

        $this->postJson("/api/v1/customer/bookings/{$booking->id}/cancel/send-otp")->assertOk();

        $response = $this->putJson("/api/v1/customer/bookings/{$booking->id}/cancel", [
            'cancellation_reason' => 'No longer needed',
            'otp' => '123456',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.booking_status', 'cancelled')
            ->assertJsonPath('data.refund_status', 'completed')
            ->assertJsonPath('data.refund_points', 300)
            ->assertJsonPath('data.refund_destination', 'points');

        // Check points refunded
        $this->assertEquals(300, $user->pointTransactions()->where('type', 'earn')->sum('amount'));

        $this->assertDatabaseHas('booking_visits', ['id' => $visit1->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('booking_visits', ['id' => $visit2->id, 'status' => 'cancelled']);
    }

    public function test_cannot_cancel_if_starts_in_less_than_cutoff_hours(): void
    {
        $user = $this->actingAsCustomer();
        $service = Service::factory()->create(['status' => 'active', 'base_price' => 100.00]);
        ServiceRule::factory()->create([
            'service_id' => $service->id,
            'allow_cancel' => true,
            'free_cancel_cutoff_hours' => 24,
        ]);
        $address = CustomerAddress::factory()->create(['user_id' => $user->id]);

        $booking = Booking::create([
            'booking_ref' => 'BK-CUTOFF',
            'customer_id' => $user->id,
            'service_id' => $service->id,
            'address_id' => $address->id,
            'booking_date' => Carbon::now()->toDateString(),
            'start_time' => Carbon::now()->addHours(2)->format('H:i:s'), 
            'end_time' => Carbon::now()->addHours(4)->format('H:i:s'),
            'duration_minutes' => 120,
            'price_subtotal' => 100.00,
            'total_amount' => 100.00,
            'status' => 'Upcoming',
            'payment_status' => 'paid',
            'payment_method' => 'wallet',
        ]);

        $response = $this->postJson("/api/v1/customer/bookings/{$booking->id}/cancel/send-otp");

        $response->assertStatus(400);
        $this->assertEquals('Upcoming', $booking->fresh()->status);
    }

    public function test_cannot_cancel_if_any_visit_is_completed_or_inservice(): void
    {
        $user = $this->actingAsCustomer();
        $service = Service::factory()->create(['status' => 'active', 'base_price' => 100.00]);
        ServiceRule::factory()->create([
            'service_id' => $service->id,
            'allow_cancel' => true,
            'free_cancel_cutoff_hours' => 24,
        ]);
        $address = CustomerAddress::factory()->create(['user_id' => $user->id]);

        $booking = Booking::create([
            'booking_ref' => 'BK-PARTIAL',
            'customer_id' => $user->id,
            'service_id' => $service->id,
            'address_id' => $address->id,
            'booking_date' => Carbon::now()->addDays(2)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'duration_minutes' => 120,
            'price_subtotal' => 300.00,
            'total_amount' => 300.00,
            'status' => 'Upcoming',
            'payment_status' => 'paid',
            'payment_method' => 'wallet',
            'visit_type' => 'three_visits_per_week',
        ]);

        BookingVisit::create([
            'booking_id' => $booking->id,
            'visit_date' => $booking->booking_date,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'status' => 'completed', 
        ]);

        BookingVisit::create([
            'booking_id' => $booking->id,
            'visit_date' => Carbon::now()->addDays(4)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'status' => 'Upcoming',
        ]);

        $response = $this->postJson("/api/v1/customer/bookings/{$booking->id}/cancel/send-otp");

        $response->assertStatus(400);
        $this->assertEquals('Upcoming', $booking->fresh()->status);
    }

    public function test_cannot_cancel_with_invalid_otp(): void
    {
        $user = $this->actingAsCustomer();
        $booking = Booking::factory()->create([
            'customer_id' => $user->id,
            'status' => 'Upcoming',
        ]);

        $this->postJson("/api/v1/customer/bookings/{$booking->id}/cancel/send-otp")->assertOk();

        $response = $this->putJson("/api/v1/customer/bookings/{$booking->id}/cancel", [
            'cancellation_reason' => 'Invalid OTP test',
            'otp' => '999999',
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_details_includes_cancellation_fields(): void
    {
        $user = $this->actingAsCustomer();
        $service = Service::factory()->create(['status' => 'active']);
        $address = CustomerAddress::factory()->create(['user_id' => $user->id]);

        $booking = Booking::create([
            'booking_ref' => 'BK-ADMIN-TEST',
            'customer_id' => $user->id,
            'service_id' => $service->id,
            'address_id' => $address->id,
            'booking_date' => Carbon::now()->addDays(2)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'duration_minutes' => 120,
            'price_subtotal' => 100.00,
            'total_amount' => 100.00,
            'status' => 'cancelled',
            'payment_status' => 'refunded',
            'payment_method' => 'wallet',
            'refund_amount' => 100.00,
            'refund_status' => 'completed',
            'refund_destination' => 'wallet',
            'cancelled_by' => 'customer',
            'cancelled_at' => Carbon::now()->toDateTimeString(),
            'cancellation_reason' => 'Admin test reason',
        ]);

        $admin = $this->actingAsAdmin();

        $response = $this->getJson("/api/v1/admin/bookings/{$booking->id}");
        $response->assertOk()
            ->assertJsonPath('data.payment_method', 'wallet')
            ->assertJsonPath('data.refund_status', 'completed')
            ->assertJsonPath('data.refund_destination', 'wallet')
            ->assertJsonPath('data.cancelled_by', 'customer')
            ->assertJsonPath('data.cancellation_reason', 'Admin test reason');

        $this->assertEquals(100.00, $response->json('data.refund_amount'));
    }

    public function test_admin_can_cancel_past_cutoff_hours(): void
    {
        $user = $this->actingAsCustomer();
        $user->deposit(150.00, 'Test Deposit');

        $service = Service::factory()->create(['status' => 'active', 'base_price' => 150.00]);
        ServiceRule::factory()->create([
            'service_id' => $service->id,
            'allow_cancel' => true,
            'free_cancel_cutoff_hours' => 24,
        ]);
        $address = CustomerAddress::factory()->create(['user_id' => $user->id]);

        $booking = Booking::create([
            'booking_ref' => 'BK-ADMIN-CUTOFF',
            'customer_id' => $user->id,
            'service_id' => $service->id,
            'address_id' => $address->id,
            'booking_date' => Carbon::now()->toDateString(),
            'start_time' => Carbon::now()->addHours(2)->format('H:i:s'), // 2 hours away (< 24)
            'end_time' => Carbon::now()->addHours(4)->format('H:i:s'),
            'duration_minutes' => 120,
            'price_subtotal' => 150.00,
            'total_amount' => 150.00,
            'status' => 'Upcoming',
            'payment_status' => 'paid',
            'payment_method' => 'wallet',
        ]);

        $user->withdraw(150.00, 'Deduction simulation');
        $user->refresh();

        $admin = $this->actingAsAdmin();

        $response = $this->patchJson("/api/v1/admin/bookings/{$booking->id}/status", [
            'status' => 'cancelled',
            'note' => 'Admin cancellation override',
        ]);

        $response->assertOk();
        $this->assertEquals('cancelled', $booking->fresh()->status);
        $this->assertEquals('admin', $booking->fresh()->cancelled_by);
        $this->assertEquals('Admin cancellation override', $booking->fresh()->cancellation_reason);
        
        $user->refresh();
        $this->assertEquals(150.00, $user->wallet_balance);
    }

    public function test_admin_cannot_cancel_if_any_visit_is_completed(): void
    {
        $user = $this->actingAsCustomer();
        $service = Service::factory()->create(['status' => 'active']);
        $address = CustomerAddress::factory()->create(['user_id' => $user->id]);

        $booking = Booking::create([
            'booking_ref' => 'BK-ADMIN-COMPLETED',
            'customer_id' => $user->id,
            'service_id' => $service->id,
            'address_id' => $address->id,
            'booking_date' => Carbon::now()->addDays(2)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'duration_minutes' => 120,
            'price_subtotal' => 100.00,
            'total_amount' => 100.00,
            'status' => 'Upcoming',
            'payment_status' => 'paid',
            'payment_method' => 'wallet',
        ]);

        BookingVisit::create([
            'booking_id' => $booking->id,
            'visit_date' => $booking->booking_date,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'status' => 'completed',
        ]);

        $admin = $this->actingAsAdmin();

        $response = $this->patchJson("/api/v1/admin/bookings/{$booking->id}/status", [
            'status' => 'cancelled',
            'note' => 'Admin trying to cancel completed booking',
        ]);

        $response->assertStatus(400);
        $this->assertEquals('Upcoming', $booking->fresh()->status);
    }

    public function test_admin_cancellation_requires_note(): void
    {
        $user = $this->actingAsCustomer();
        $booking = Booking::factory()->create([
            'customer_id' => $user->id,
            'status' => 'Upcoming',
        ]);

        $admin = $this->actingAsAdmin();

        $response = $this->patchJson("/api/v1/admin/bookings/{$booking->id}/status", [
            'status' => 'cancelled',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('params.note', 'The note field is required.');
    }
}
