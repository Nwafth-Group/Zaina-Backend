<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/**
 * Admin booking detail view + status changes. Status transitions are logged to
 * booking_histories with the acting admin and an optional note. Routes sit
 * behind `auth:sanctum` + `IsAdmin`.
 */
class BookingStatusUpdateTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/bookings';

    // ---------------------------------------------------------------------
    // Detail view
    // ---------------------------------------------------------------------

    public function test_admin_can_view_booking_details_with_activity(): void
    {
        $this->actingAsAdmin();
        $booking = Booking::factory()->create();

        $this->getJson(self::ENDPOINT.'/'.$booking->id)
            ->assertOk()
            ->assertJsonPath('data.id', $booking->id)
            ->assertJsonStructure([
                'data' => [
                    'booking_ref', 'status', 'payment_status',
                    'customer' => ['name', 'phone'],
                    'service_name', 'booking_date', 'time_slot',
                    'price_summary' => ['subtotal', 'tax', 'discount', 'total'],
                    'workers', 'activity',
                ],
            ]);
    }

    public function test_guest_cannot_view_booking_details(): void
    {
        $booking = Booking::factory()->create();

        $this->getJson(self::ENDPOINT.'/'.$booking->id)->assertStatus(401);
    }

    // ---------------------------------------------------------------------
    // Status changes
    // ---------------------------------------------------------------------

    public function test_admin_can_change_status_and_it_is_logged(): void
    {
        $admin = $this->actingAsAdmin();
        $booking = Booking::factory()->create([
            'status' => 'Upcoming',
            'booking_date' => \Carbon\Carbon::today('Africa/Cairo')->toDateString(),
        ]);

        $this->patchJson(self::ENDPOINT.'/'.$booking->id.'/status', ['status' => 'inService'])
            ->assertOk()
            ->assertJsonPath('data.status', 'inService');

        $this->assertSame('inService', $booking->fresh()->status);
        $this->assertDatabaseHas('booking_histories', [
            'booking_id' => $booking->id,
            'old_status' => 'Upcoming',
            'new_status' => 'inService',
            'created_by' => $admin->id,
        ]);
    }

    public function test_admin_can_mark_booking_completed(): void
    {
        $this->actingAsAdmin();
        $booking = Booking::factory()->create(['status' => 'inService']);

        $this->patchJson(self::ENDPOINT.'/'.$booking->id.'/status', ['status' => 'completed'])
            ->assertOk();

        $this->assertSame('completed', $booking->fresh()->status);
    }

    public function test_admin_can_cancel_booking_with_a_note(): void
    {
        $this->actingAsAdmin();
        $booking = Booking::factory()->create(['status' => 'Upcoming']);

        $this->patchJson(self::ENDPOINT.'/'.$booking->id.'/status', [
            'status' => 'cancelled',
            'note' => 'Customer requested cancellation',
        ])->assertOk();

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertDatabaseHas('booking_histories', [
            'booking_id' => $booking->id,
            'new_status' => 'cancelled',
            'note' => 'Cancelled by admin. Reason: Customer requested cancellation',
        ]);
    }

    public function test_status_must_be_a_valid_value(): void
    {
        $this->actingAsAdmin();
        $booking = Booking::factory()->create();

        $this->patchJson(self::ENDPOINT.'/'.$booking->id.'/status', ['status' => 'archived'])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['status']]);
    }

    public function test_status_is_required(): void
    {
        $this->actingAsAdmin();
        $booking = Booking::factory()->create();

        $this->patchJson(self::ENDPOINT.'/'.$booking->id.'/status', [])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['status']]);
    }

    public function test_admin_can_download_booking_contract(): void
    {
        $this->actingAsAdmin();
        $booking = Booking::factory()->create();

        $response = $this->get(self::ENDPOINT.'/'.$booking->id.'/contract');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
