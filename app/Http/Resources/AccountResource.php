<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'type'           => $this->type,
            'contact_type'   => class_basename($this->contact_type),
            'contact_id'     => $this->contact_id,
            'contact_name'   => $this->whenLoaded('contact', fn() => $this->contact?->name),
            'reference_type' => class_basename($this->reference_type),
            'reference_id'   => $this->reference_id,
            'reference_code' => $this->whenLoaded('reference', fn() =>
                $this->reference?->invoice_code ?? $this->reference?->po_code ?? null
            ),
            'total_amount'   => $this->total_amount,
            'paid_amount'    => $this->paid_amount,
            'balance'        => $this->balance,
            'due_date'       => $this->due_date?->toDateString(),
            'status'         => $this->status,
            'created_at'     => $this->created_at?->toDateTimeString(),
        ];
    }
}
