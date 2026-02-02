<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'order_code' => $this->order_code,
            'order_date' => $this->order_date->format('Y-m-d'),
            'order_date_formatted' => $this->order_date->format('d/m/Y'),
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'status_color' => $this->getStatusColor(),

            'total_amount' => (float) $this->total_amount,
            'total_amount_formatted' => number_format($this->total_amount, 0, ',', '.') . ' VNĐ',

            // Customer info
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'customer_name' => $this->customer?->name,

            // Order items
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->when(
                isset($this->items_count),
                $this->items_count,
                fn() => $this->items->count()
            ),
            'total_quantity' => $this->when(
                $this->relationLoaded('items'),
                fn() => $this->items->sum('quantity')
            ),

            // Timestamps
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'created_at_human' => $this->created_at->diffForHumans(),
        ];
    }

    /**
     * Get status label
     */
    private function getStatusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Chờ xử lý',
            'processing' => 'Đang xử lý',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Đã hủy',
            default => 'Không xác định',
        };
    }

    /**
     * Get status color for UI
     */
    private function getStatusColor(): string
    {
        return match ($this->status) {
            'pending' => 'yellow',
            'processing' => 'blue',
            'completed' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }
}
