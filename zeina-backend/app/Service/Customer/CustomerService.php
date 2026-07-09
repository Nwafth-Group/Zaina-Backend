<?php

namespace App\Service\Customer;

use App\Models\BookingAssignment;
use App\Models\Category;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class CustomerService
{
    /**
     * List active services with optional type filtering under active categories.
     */
    public function listServices(array $filters): \Illuminate\Database\Eloquent\Collection
    {
        $query = Service::with(['category', 'media', 'startingPrice'])
            ->where('status', 'active');

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->get();
    }


    /**
     * Show service details if service and its parent category are active.
     */
    public function showService(Service $service): Service
    {
        if ($service->status !== 'active') {
            abort(404, __('messages.service_not_available'));
        }

        $service->load(['rules', 'media', 'schedules']);

        $userId = auth('sanctum')->id();
        $activeWorkers = $service->workers()->where('status', 'active')->get();

        $preferredIds = [];
        if ($userId) {
            $preferredIds = \App\Models\BookingAssignment::whereHas('booking', function($q) use ($userId, $service) {
                    $q->where('customer_id', $userId)
                      ->where('service_id', $service->id)
                      ->where('status', 'completed');
                })
                ->pluck('worker_id')
                ->unique()
                ->toArray();
        }

        $service->setRelation('preferred_workers', $activeWorkers->whereIn('id', $preferredIds)->values());

        return $service;
    }
}
