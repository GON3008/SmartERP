<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'po_code'       => $this->po_code,
            'supplier_id'   => $this->supplier_id,
            'supplier'      => $this->whenLoaded('supplier', fn() => [
                'id'   => $this->supplier->id,
                'name' => $this->supplier->name,
            ]),
            'order_date'    => $this->order_date?->toDateString(),
            'expected_date' => $this->expected_date?->toDateString(),
            'status'        => $this->status,
            'total_amount'  => $this->total_amount,
            'notes'         => $this->notes,
            'items'         => $this->whenLoaded('items', fn() =>
                $this->items->map(fn($item) => [
                    'id'                => $item->id,
                    'product_id'        => $item->product_id,
                    'product_name'      => $item->product?->name,
                    'product_sku'       => $item->product?->sku,
                    'quantity'          => $item->quantity,
                    'unit_price'        => $item->unit_price,
                    'total_price'       => $item->total_price,
                    'received_quantity' => $item->received_quantity,
                ])
            ),
            'created_at'    => $this->created_at?->toDateTimeString(),
            'updated_at'    => $this->updated_at?->toDateTimeString(),
        ];
    }
}
