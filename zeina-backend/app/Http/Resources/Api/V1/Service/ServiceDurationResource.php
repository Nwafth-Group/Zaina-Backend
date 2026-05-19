<?php

namespace App\Http\Resources\Api\V1\Service;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceDurationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {   
       $typehour = $this->service->type === 'hourly';
       $typemoqem = $this->service->type === 'moqem';
        return [
            'id' => $this->id,
            'service_period_hours' => $this->service_period_hours,
            'allowed_contract_durations' => $typemoqem ? $this->allowed_contract_durations : null,
            'min_contract_months' => $typemoqem ? $this->min_contract_months : null,
        ];
    }
}
