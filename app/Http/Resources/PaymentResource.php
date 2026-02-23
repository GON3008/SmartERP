<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'payment_code'   => $this->payment_code,
            'payable_type'   => class_basename($this->payable_type),
            'payable_id'     => $this->payable_id,
            'payable'        => $this->whenLoaded('payable'),
            'amount'         => $this->amount,
            'payment_method' => $this->payment_method,
            'payment_date'   => $this->payment_date?->toDateString(),
            'reference'      => $this->reference,
            'notes'          => $this->notes,
            'created_at'     => $this->created_at?->toDateTimeString(),
        ];
    }
}
