<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ProductionOrderResource;

class ProductionLogResource extends JsonResource
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
            'production_order_id' => $this->production_order_id,
            'quantity_produced' => $this->quantity_produced,
            'quantity_defective' => $this->quantity_defective,
            'notes' => $this->notes,
            'logged_at' => $this->logged_at,

            // Calculated fields
            'quantity_good' => $this->quantity_produced - $this->quantity_defective,
            'defect_rate' => $this->quantity_produced > 0
                ? round(($this->quantity_defective / $this->quantity_produced) * 100, 2)
                : 0,

            // Relationships
            'production_order' => new ProductionOrderResource($this->whenLoaded('productionOrder')),

            // Timestamps
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
