<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\WarehouseResource;

class InventoryResource extends JsonResource
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
            'product_id' => $this->product_id,
            'warehouse_id' => $this->warehouse_id,
            'quantity' => $this->quantity,
            'min_stock' => $this->min_stock,
            'max_stock' => $this->max_stock,

            // Stock status
            'stock_status' => $this->getStockStatus(),
            'stock_status_color' => $this->getStockStatusColor(),
            'is_low_stock' => $this->quantity <= $this->min_stock,
            'is_overstock' => $this->quantity >= $this->max_stock,

            // Calculated fields
            'stock_percentage' => $this->max_stock > 0
                ? round(($this->quantity / $this->max_stock) * 100, 2)
                : 0,
            'reorder_needed' => $this->quantity < $this->min_stock,
            'available_capacity' => $this->max_stock - $this->quantity,

            // Relationships
            'product' => new ProductResource($this->whenLoaded('product')),
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),

            // Timestamps
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * Get stock status label
     */
    protected function getStockStatus(): string
    {
        if ($this->quantity <= 0) {
            return 'Hết hàng';
        } elseif ($this->quantity <= $this->min_stock) {
            return 'Tồn kho thấp';
        } elseif ($this->quantity >= $this->max_stock) {
            return 'Tồn kho cao';
        } else {
            return 'Bình thường';
        }
    }

    /**
     * Get stock status color
     */
    protected function getStockStatusColor(): string
    {
        if ($this->quantity <= 0) {
            return 'danger';
        } elseif ($this->quantity <= $this->min_stock) {
            return 'warning';
        } elseif ($this->quantity >= $this->max_stock) {
            return 'info';
        } else {
            return 'success';
        }
    }
}
