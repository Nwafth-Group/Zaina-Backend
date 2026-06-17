<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/**
 * Admin dashboard statistics endpoint (`/api/v1/admin/dashboard/stats`),
 * behind `auth:sanctum` + `IsAdmin`.
 */
class DashboardStatsTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/dashboard/stats';

    public function test_guest_cannot_view_dashboard(): void
    {
        $this->getJson(self::ENDPOINT)->assertStatus(401);
    }

    public function test_customer_cannot_view_dashboard(): void
    {
        $this->actingAsCustomer();

        $this->getJson(self::ENDPOINT)->assertStatus(403);
    }

    public function test_dashboard_returns_the_expected_shape(): void
    {
        $this->actingAsAdmin();

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'cards' => ['revenue', 'currency', 'bookings_total', 'bookings_upcoming', 'active_services', 'workers', 'customers'],
                    'bookings_by_status' => ['Upcoming', 'inService', 'completed', 'cancelled'],
                    'payments_by_status' => ['unpaid', 'paid', 'refunded'],
                    'trend' => [['date', 'label', 'count', 'revenue']],
                    'top_services',
                    'upcoming',
                ],
            ])
            ->assertJsonCount(14, 'data.trend');
    }

    public function test_dashboard_aggregates_bookings(): void
    {
        $this->actingAsAdmin();
        Booking::factory()->create(['status' => 'completed', 'total_amount' => 250]);
        Booking::factory()->create(['status' => 'completed', 'total_amount' => 150]);
        Booking::factory()->create(['status' => 'Upcoming']);

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.cards.revenue', 400)
            ->assertJsonPath('data.cards.bookings_total', 3)
            ->assertJsonPath('data.cards.bookings_upcoming', 1)
            ->assertJsonPath('data.bookings_by_status.completed', 2)
            ->assertJsonPath('data.bookings_by_status.Upcoming', 1);
    }
}
