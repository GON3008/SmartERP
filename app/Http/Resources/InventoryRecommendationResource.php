<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ProductResource;

class InventoryRecommendationResource extends JsonResource
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
            'avg_daily_sales' => $this->avg_daily_sales,
            'forecast_days' => $this->forecast_days,
            'recommended_quantity' => $this->recommended_quantity,
            'ai_summary' => $this->ai_summary,

            // Calculated fields
            'estimated_stockout_date' => $this->when(
                $this->product && $this->avg_daily_sales > 0,
                function () {
                    $currentStock = $this->product->inventories->sum('quantity') ?? 0;
                    $daysRemaining = $currentStock / $this->avg_daily_sales;
                    return \Carbon\Carbon::now()->addDays($daysRemaining)->format('Y-m-d');
                }
            ),

            // Relationships
            'product' => new ProductResource($this->whenLoaded('product')),

            // Timestamps
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
