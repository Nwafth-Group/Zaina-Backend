<?php

namespace Tests\Feature\Admin;

use App\Models\Area;
use App\Models\Booking;
use App\Models\Category;
use App\Models\City;
use App\Models\CustomerAddress;
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

    public function test_filter_by_categories_matches_any_selected(): void
    {
        $this->actingAsAdmin();
        $catA = Category::factory()->create();
        $catB = Category::factory()->create();
        $catC = Category::factory()->create();

        Booking::factory()->create(['service_id' => Service::factory()->create(['category_id' => $catA->id])]);
        Booking::factory()->create(['service_id' => Service::factory()->create(['category_id' => $catB->id])]);
        Booking::factory()->create(['service_id' => Service::factory()->create(['category_id' => $catC->id])]);

        // Multi-select: bookings whose service belongs to A or B (not C).
        $this->getJson(self::ENDPOINT.'?category_ids='.$catA->id.','.$catB->id)
            ->assertOk()
            ->assertJsonCount(2, 'data.items');
    }

    public function test_filter_by_price_range(): void
    {
        $this->actingAsAdmin();
        Booking::factory()->create(['total_amount' => 50]);
        Booking::factory()->create(['total_amount' => 200]);
        Booking::factory()->create(['total_amount' => 500]);

        $this->getJson(self::ENDPOINT.'?min_amount=100&max_amount=300')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.total_amount', 200);
    }

    public function test_filter_by_city_and_area(): void
    {
        $this->actingAsAdmin();

        $cityA = City::factory()->create();
        $areaA1 = Area::factory()->create(['city_id' => $cityA->id]);
        $areaA2 = Area::factory()->create(['city_id' => $cityA->id]);
        $cityB = City::factory()->create();
        $areaB1 = Area::factory()->create(['city_id' => $cityB->id]);

        $bookingInA1 = Booking::factory()->create(['address_id' => CustomerAddress::factory()->create(['area_id' => $areaA1->id])]);
        Booking::factory()->create(['address_id' => CustomerAddress::factory()->create(['area_id' => $areaA2->id])]);
        Booking::factory()->create(['address_id' => CustomerAddress::factory()->create(['area_id' => $areaB1->id])]);

        // City A has two bookings (across its two areas).
        $this->getJson(self::ENDPOINT.'?city_id='.$cityA->id)
            ->assertOk()
            ->assertJsonCount(2, 'data.items');

        // Narrowing to a single area returns just that one.
        $this->getJson(self::ENDPOINT.'?area_id='.$areaA1->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $bookingInA1->id);
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
