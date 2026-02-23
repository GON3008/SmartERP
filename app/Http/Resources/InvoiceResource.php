<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'invoice_code' => $this->invoice_code,
            'order_id'     => $this->order_id,
            'customer_id'  => $this->customer_id,
            'customer'     => $this->whenLoaded('customer', fn() => [
                'id'   => $this->customer->id,
                'name' => $this->customer->name,
            ]),
            'order' => $this->whenLoaded('order', fn() => [
                'id'           => $this->order->id,
                'total_amount' => $this->order->total_amount,
                'status'       => $this->order->status,
            ]),
            'invoice_date' => $this->invoice_date?->toDateString(),
            'due_date'     => $this->due_date?->toDateString(),
            'subtotal'     => $this->subtotal,
            'tax_rate'     => $this->tax_rate,
            'tax_amount'   => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'status'       => $this->status,
            'notes'        => $this->notes,
            'payments'     => $this->whenLoaded('payments'),
            'created_at'   => $this->created_at?->toDateTimeString(),
            'updated_at'   => $this->updated_at?->toDateTimeString(),
        ];
    }
}
