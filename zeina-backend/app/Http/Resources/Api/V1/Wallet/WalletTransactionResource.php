<?php

namespace App\Http\Resources\Api\V1\Wallet;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletTransactionResource extends JsonResource
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
            'type' => $this->type, // credit أو debit
            'amount' => (float) $this->amount,
            'title' => $this->title,
            'reference_id' => $this->reference_id,
            'created_at' => $this->created_at->format('d M Y'), // تنسيق التاريخ مثل الفيجما "20 Nov 2026"
            'time' => $this->created_at->format('h:i A'),
        ];
    }
}
