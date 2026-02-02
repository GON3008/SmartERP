<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'sku' => $this->sku,
            'name' => $this->name,
            'category' => $this->category,
            'unit' => $this->unit,
            'price' => (float) $this->price,
            'price_formatted' => number_format($this->price, 0, ',', '.') . ' VNĐ',
            'min_stock' => $this->min_stock,
            'total_stock' => $this->when(
                $this->relationLoaded('inventories'),
                fn() => $this->inventories->sum('quantity')
            ),

            'stock_status' => $this->when(
                $this->relationLoaded('inventories'),
                function () {
                    $totalStock = $this->inventories->sum('quantity');
                    if ($totalStock == 0) return 'out_of_stock';
                    if ($totalStock <= $this->min_stock) return 'low_stock';
                    return 'in_stock';
                }
            ),

            'stock_status_label' => $this->when(
                $this->relationLoaded('inventories'),
                function () {
                    $totalStock = $this->inventories->sum('quantity');
                    if ($totalStock == 0) return 'Hết hàng';
                    if ($totalStock <= $this->min_stock) return 'Sắp hết';
                    return 'Còn hàng';
                }
            ),

            // Relationships
            'inventories' => InventoryResource::collection($this->whenLoaded('inventories')),

            // Counts
            'inventories_count' => $this->when(
                isset($this->inventories_count),
                $this->inventories_count
            ),

            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
