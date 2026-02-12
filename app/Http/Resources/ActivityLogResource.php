<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
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
            'user_id' => $this->user_id,
            'action' => $this->action,
            'model_type' => $this->model_type,
            'model_id' => $this->model_id,
            'description' => $this->description,
            
            // Formatted fields
            'action_label' => $this->getActionLabel(),
            'model_name' => $this->getModelName(),
            'time_ago' => $this->created_at?->diffForHumans(),
            
            // Relationships
            'user' => new UserResource($this->whenLoaded('user')),
            
            // Timestamps
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * Get action label in Vietnamese
     */
    protected function getActionLabel(): string
    {
        return match($this->action) {
            'created' => 'Tạo mới',
            'updated' => 'Cập nhật',
            'deleted' => 'Xóa',
            'viewed' => 'Xem',
            'exported' => 'Xuất',
            'imported' => 'Nhập',
            default => $this->action,
        };
    }

    /**
     * Get model name in Vietnamese
     */
    protected function getModelName(): string
    {
        $modelMap = [
            'employees' => 'Nhân viên',
            'products' => 'Sản phẩm',
            'orders' => 'Đơn hàng',
            'customers' => 'Khách hàng',
            'warehouses' => 'Kho',
            'users' => 'Người dùng',
            'roles' => 'Vai trò',
            'departments' => 'Phòng ban',
            'positions' => 'Chức vụ',
        ];

        return $modelMap[$this->model_type] ?? $this->model_type;
    }
}
