<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use App\Http\Resources\ProductionLogResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ProductResource;

class ProductionOrderResource extends JsonResource
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
            'quantity' => $this->quantity,
            'status' => $this->status,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'notes' => $this->notes,

            // Status badge
            'status_label' => $this->getStatusLabel(),
            'status_color' => $this->getStatusColor(),

            // Calculated fields
            'duration_days' => $this->when(
                $this->start_date && $this->end_date,
                function () {
                    return \Carbon\Carbon::parse($this->start_date)
                        ->diffInDays(\Carbon\Carbon::parse($this->end_date));
                }
            ),

            // Relationships
            'product' => new ProductResource($this->whenLoaded('product')),
            'logs' => ProductionLogResource::collection($this->whenLoaded('logs')),
            'logs_count' => $this->when(isset($this->logs_count), $this->logs_count),

            // Timestamps
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * Get status label
     */
    protected function getStatusLabel(): string
    {
        return match($this->status) {
            'pending' => 'Chờ xử lý',
            'in_progress' => 'Đang sản xuất',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Đã hủy',
            default => $this->status,
        };
    }

    /**
     * Get status color
     */
    protected function getStatusColor(): string
    {
        return match($this->status) {
            'pending' => 'warning',
            'in_progress' => 'info',
            'completed' => 'success',
            'cancelled' => 'danger',
            default => 'secondary',
        };
    }
}
