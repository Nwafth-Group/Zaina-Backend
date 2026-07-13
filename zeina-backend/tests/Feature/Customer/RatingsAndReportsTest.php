<?php

namespace Tests\Feature\Customer;

use App\Models\Category;
use App\Models\Service;
use App\Models\Worker;
use App\Models\CustomerAddress;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class RatingsAndReportsTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsAdmin;

    protected $customer;
    protected $admin;
    protected $service;
    protected $worker;
    protected $booking;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create a customer
        $this->customer = User::factory()->create([
            'type' => 'customer',
            'phone_number' => '+201111111111',
            'verification_status' => 'approved',
            'status' => 'active',
        ]);

        // 2. Create an admin
        $this->admin = User::factory()->create([
            'type' => 'super_admin',
            'phone_number' => '0599000001',
            'verification_status' => 'approved',
            'status' => 'active',
        ]);

        // 3. Set up Category and Service
        $category = Category::factory()->create(['name_en' => 'Hourly Services', 'status' => 'active']);
        $this->service = Service::factory()->create([
            'category_id' => $category->id,
            'status' => 'active',
            'service_code' => 'HOSPITALITY_WORKER_HOURLY',
            'pricing_type' => 'per_hour',
            'base_price' => 50.00,
            'min_workers' => 1,
            'max_workers' => 3,
            'avg_rating' => 0.00,
        ]);

        // 4. Create a Worker
        $this->worker = Worker::factory()->create([
            'name' => 'Fatma Worker',
            'status' => 'active',
            'avg_rating' => 0.00,
        ]);

        // 5. Create a Completed Booking for Ratings and Report tests
        $address = CustomerAddress::factory()->create([
            'user_id' => $this->customer->id,
            'is_default' => true,
        ]);

        $this->booking = Booking::create([
            'booking_ref' => 'BK-1000',
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'address_id' => $address->id,
            'booking_date' => Carbon::today()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '14:00:00',
            'duration' => 4,
            'duration_minutes' => 240,
            'workers_count' => 1,
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 200,
            'vat' => 0,
            'grand_total' => 200,
        ]);

        // Assign worker to booking
        BookingAssignment::create([
            'booking_id' => $this->booking->id,
            'worker_id' => $this->worker->id,
            'status' => 'assigned',
        ]);
    }

    /** @test */
    public function customer_can_rate_completed_booking_successfully()
    {
        $payload = [
            'service_rating' => 5,
            'service_comment' => 'Superb hospitality service!',
            'workers' => [
                [
                    'worker_id' => $this->worker->id,
                    'rating' => 4,
                    'comment' => 'Very punctual worker.',
                ]
            ]
        ];

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/customer/bookings/{$this->booking->id}/review", $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => __('messages.review_submitted_successfully') ?? 'Review submitted successfully. Thank you!',
        ]);

        // Assert reviews are saved in DB with correct percentage calculations
        $this->assertDatabaseHas('reviews', [
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'reviewable_type' => Service::class,
            'reviewable_id' => $this->service->id,
            'rating' => 5,
            'rating_percentage' => 100.00,
            'comment' => 'Superb hospitality service!',
        ]);

        $this->assertDatabaseHas('reviews', [
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'reviewable_type' => Worker::class,
            'reviewable_id' => $this->worker->id,
            'rating' => 4,
            'rating_percentage' => 80.00,
            'comment' => 'Very punctual worker.',
        ]);

        // Assert average ratings are updated and rounded to 2 decimals
        $this->assertEquals(5.00, $this->service->fresh()->avg_rating);
        $this->assertEquals(4.00, $this->worker->fresh()->avg_rating);
    }

    /** @test */
    public function customer_cannot_submit_duplicate_ratings_for_same_booking()
    {
        // First review
        Review::create([
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'reviewable_type' => Service::class,
            'reviewable_id' => $this->service->id,
            'rating' => 5,
            'rating_percentage' => 100.00,
        ]);

        $payload = [
            'service_rating' => 3,
            'service_comment' => 'Duplicate attempt',
            'workers' => []
        ];

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/customer/bookings/{$this->booking->id}/review", $payload);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => __('messages.booking_already_reviewed') ?? 'This booking has already been rated.',
        ]);
    }

    /** @test */
    public function customer_cannot_rate_incomplete_bookings()
    {
        $upcomingBooking = Booking::create([
            'booking_ref' => 'BK-1001',
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'address_id' => $this->booking->address_id,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '14:00:00',
            'duration' => 4,
            'duration_minutes' => 240,
            'workers_count' => 1,
            'status' => 'Upcoming',
            'payment_status' => 'paid',
            'subtotal' => 200,
            'vat' => 0,
            'grand_total' => 200,
        ]);

        $payload = [
            'service_rating' => 5,
            'workers' => []
        ];

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/customer/bookings/{$upcomingBooking->id}/review", $payload);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => __('messages.invalid_booking_status_for_review') ?? 'Sorry, you can only review after the service is completed.',
        ]);
    }

    /** @test */
    public function customer_can_report_booking_service_issue()
    {
        $payload = [
            'reason' => 'worker_didnt_show_up',
            'description' => 'The worker did not show up at all.',
            'worker_id' => $this->worker->id,
        ];

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/customer/bookings/{$this->booking->id}/report", $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => __('messages.booking_reported_successfully') ?? 'Booking reported successfully.',
        ]);

        // Verify report details
        $report = Report::where('booking_id', $this->booking->id)->first();
        $this->assertNotNull($report);
        $this->assertStringStartsWith('REP-', $report->report_number);
        $this->assertEquals('submitted', $report->status);
        $this->assertEquals($this->service->id, $report->service_id);
        $this->assertEquals($this->worker->id, $report->worker_id);

        // Verify history record
        $this->assertDatabaseHas('report_histories', [
            'report_id' => $report->id,
            'old_status' => null,
            'new_status' => 'submitted',
            'created_by' => $this->customer->id,
        ]);
    }

    /** @test */
    public function customer_cannot_submit_duplicate_open_reports_for_same_reason()
    {
        // First report
        Report::create([
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'report_number' => 'REP-111111',
            'reason' => 'worker_didnt_show_up',
            'status' => 'submitted',
        ]);

        $payload = [
            'reason' => 'worker_didnt_show_up',
            'description' => 'Duplicate description',
        ];

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/customer/bookings/{$this->booking->id}/report", $payload);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => __('messages.open_report_exists') ?? 'You already have an open report for this reason on this booking.',
        ]);
    }

    /** @test */
    public function admin_can_list_filter_and_assign_reports()
    {
        $report = Report::create([
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'report_number' => 'REP-999999',
            'reason' => 'late_arrival',
            'status' => 'submitted',
        ]);

        // 1. Admin index list
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports?status=submitted');

        $response->assertStatus(200);
        $response->assertJsonFragment(['report_number' => 'REP-999999']);

        // 2. Admin assign report
        $assignPayload = [
            'admin_id' => $this->admin->id,
        ];
        $assignResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/reports/{$report->id}/assign", $assignPayload);

        $assignResponse->assertStatus(200);
        $this->assertEquals($this->admin->id, $report->fresh()->assigned_admin_id);

        // 3. Admin update status and add notes
        $statusPayload = [
            'status' => 'under_review',
            'note' => 'Admin started reviewing the late arrival case.',
        ];
        $statusResponse = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/reports/{$report->id}/status", $statusPayload);

        $statusResponse->assertStatus(200);
        $this->assertEquals('under_review', $report->fresh()->status);

        $this->assertDatabaseHas('report_histories', [
            'report_id' => $report->id,
            'old_status' => 'submitted',
            'new_status' => 'under_review',
            'note' => 'Admin started reviewing the late arrival case.',
            'created_by' => $this->admin->id,
        ]);
    }
}
