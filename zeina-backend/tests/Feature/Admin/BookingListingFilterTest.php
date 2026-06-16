<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/**
 * Admin bookings listing (`/api/v1/admin/bookings`): read-only, behind
 * `auth:sanctum` + `IsAdmin`. Filterable by status, payment_status, service,
 * date range and a free-text search (ref / customer / service), with a safe
 * sort allow-list.
 */
class BookingListingFilterTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/bookings';

    // ---------------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------------

    public function test_guest_cannot_list_bookings(): void
    {
        $this->getJson(self::ENDPOINT)->assertStatus(401);
    }

    public function test_customer_cannot_list_bookings(): void
    {
        $this->actingAsCustomer();

        $this->getJson(self::ENDPOINT)->assertStatus(403);
    }

    public function test_admin_can_list_bookings_paginated(): void
    {
        $this->actingAsAdmin();
        Booking::factory()->count(3)->create();

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonCount(3, 'data.items')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'items' => [['id', 'booking_ref', 'customer_name', 'service_name', 'status', 'payment_status', 'total_amount']],
                    'links',
                    'meta',
                ],
            ]);
    }

    // ---------------------------------------------------------------------
    // Filters
    // ---------------------------------------------------------------------

    public function test_filter_by_status(): void
    {
        $this->actingAsAdmin();
        Booking::factory()->create(['status' => 'Upcoming']);
        Booking::factory()->create(['status' => 'completed']);

        $this->getJson(self::ENDPOINT.'?status=completed')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.status', 'completed');
    }

    public function test_filter_by_payment_status(): void
    {
        $this->actingAsAdmin();
        Booking::factory()->create(['payment_status' => 'paid']);
        Booking::factory()->create(['payment_status' => 'unpaid']);

        $this->getJson(self::ENDPOINT.'?payment_status=paid')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.payment_status', 'paid');
    }

    public function test_filter_by_service(): void
    {
        $this->actingAsAdmin();
        $service = Service::factory()->create();
        Booking::factory()->create(['service_id' => $service->id]);
        Booking::factory()->create();

        $this->getJson(self::ENDPOINT.'?service_id='.$service->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    public function test_filter_by_date_range(): void
    {
        $this->actingAsAdmin();
        Booking::factory()->create(['booking_date' => '2026-01-10']);
        Booking::factory()->create(['booking_date' => '2026-03-20']);

        $this->getJson(self::ENDPOINT.'?date_from=2026-03-01&date_to=2026-03-31')
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    // ---------------------------------------------------------------------
    // Search
    // ---------------------------------------------------------------------

    public function test_search_by_booking_ref(): void
    {
        $this->actingAsAdmin();
        Booking::factory()->create(['booking_ref' => 'BKG-FINDME-01']);
        Booking::factory()->create(['booking_ref' => 'BKG-OTHER-02']);

        $this->getJson(self::ENDPOINT.'?search=FINDME')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.booking_ref', 'BKG-FINDME-01');
    }

    public function test_search_by_customer_name(): void
    {
        $this->actingAsAdmin();
        $customer = User::factory()->customer()->create(['first_name' => 'Zahra', 'last_name' => 'Ali']);
        Booking::factory()->create(['customer_id' => $customer->id]);
        Booking::factory()->create();

        $this->getJson(self::ENDPOINT.'?search=Zahra')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.customer_name', 'Zahra Ali');
    }

    // ---------------------------------------------------------------------
    // Sorting
    // ---------------------------------------------------------------------

    public function test_sort_by_total_amount_descending(): void
    {
        $this->actingAsAdmin();
        Booking::factory()->create(['total_amount' => 100, 'booking_ref' => 'LOW']);
        Booking::factory()->create(['total_amount' => 500, 'booking_ref' => 'HIGH']);

        $this->getJson(self::ENDPOINT.'?sort_by=total_amount&sort_dir=desc')
            ->assertOk()
            ->assertJsonPath('data.items.0.booking_ref', 'HIGH');
    }

    public function test_unknown_sort_column_is_ignored(): void
    {
        $this->actingAsAdmin();
        Booking::factory()->create(['booking_ref' => 'SOLO']);

        // A bogus sort_by must not error (allow-list falls back to created_at).
        $this->getJson(self::ENDPOINT.'?sort_by=hacker&sort_dir=asc')
            ->assertOk()
            ->assertJsonFragment(['booking_ref' => 'SOLO']);
    }
}
