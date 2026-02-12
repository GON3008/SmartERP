<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use App\Http\Resources\StockInResource;
use App\Http\Resources\StockOutResource;
use App\Http\Resources\InventoryResource;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
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
            'name' => $this->name,
            'location' => $this->location,
            'capacity' => $this->capacity,

            // Relationships
            'inventories' => InventoryResource::collection($this->whenLoaded('inventories')),
            'inventories_count' => $this->when(isset($this->inventories_count), $this->inventories_count),
            'stock_ins' => StockInResource::collection($this->whenLoaded('stockIns')),
            'stock_outs' => StockOutResource::collection($this->whenLoaded('stockOuts')),

            // Timestamps
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
