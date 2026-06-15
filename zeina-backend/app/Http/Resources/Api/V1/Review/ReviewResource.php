<?php

namespace App\Http\Resources\Api\V1\Review;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
{  
    $this->load(['customer.media']); // تحميل الوسائط الخاصة بالعميل لتجنب مشكلة N+1

    // حساب عدد الأشهر التي مرت من تاريخ الإنشاء وحتى الوقت الحالي
    return [
        'id' => $this->id, 
        'rating' => $this->rating, 
        'comment' => $this->comment,
        'customer' => [
            'id' => $this->customer->id,
            'first_name' => $this->customer->first_name,
            'last_name' => $this->customer->last_name,
        ],
        // استبدال التاريخ بصيغة الأشهر
       'created_since' => $this->created_at->diffForHumans(),
    ];
}
}
