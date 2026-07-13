<?php

namespace App\Http\Resources\Api\V1\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminReportDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'report_number' => $this->report_number,
            'booking_id' => $this->booking_id,
            'booking_ref' => $this->booking->booking_ref ?? null,
            'booking_date' => $this->booking->booking_date ?? null,
            'booking_start_time' => $this->booking->start_time ?? null,
            'booking_duration' => $this->booking->duration_minutes ? (int) ceil($this->booking->duration_minutes / 60) : null,
            'booking_amount' => $this->booking->total_amount ?? 0,
            'booking_payment_status' => $this->booking->payment_status ?? null,
            
            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->full_name ?? ($this->customer->first_name . ' ' . $this->customer->last_name),
                'phone_number' => $this->customer->phone_number,
                'email' => $this->customer->email,
            ] : null,

            'service' => $this->service ? [
                'id' => $this->service->id,
                'name_ar' => $this->service->name_ar,
                'name_en' => $this->service->name_en,
            ] : null,

            'worker' => $this->worker ? [
                'id' => $this->worker->id,
                'name' => $this->worker->name,
                'phone_number' => $this->worker->phone_number ?? null,
            ] : null,

            'assigned_admin' => $this->assignedAdmin ? [
                'id' => $this->assignedAdmin->id,
                'name' => $this->assignedAdmin->full_name ?? ($this->assignedAdmin->first_name . ' ' . $this->assignedAdmin->last_name),
            ] : null,

            'reason' => $this->reason,
            'description' => $this->description,
            'admin_notes' => $this->admin_notes,
            'status' => $this->status,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),

            'history' => $this->histories ? $this->histories->map(function ($history) {
                return [
                    'id' => $history->id,
                    'old_status' => $history->old_status,
                    'new_status' => $history->new_status,
                    'note' => $history->note,
                    'created_by_name' => $history->createdBy ? ($history->createdBy->full_name ?? ($history->createdBy->first_name . ' ' . $history->createdBy->last_name)) : 'System',
                    'created_at' => $history->created_at->toDateTimeString(),
                ];
            }) : [],
        ];
    }
}
