<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
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
            'employee_code' => $this->employee_code,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'address' => $this->address,
            'email' => $this->email,
            'hire_date' => $this->hire_date,
            'status' => $this->status,
            
            // Foreign keys
            'user_id' => $this->user_id,
            'position_id' => $this->position_id,
            'department_id' => $this->department_id,
            
            // Relationships (loaded conditionally)
            'user' => $this->whenLoaded('user'),
            'position' => $this->whenLoaded('position'),
            'department' => $this->whenLoaded('department'),
            'attendances' => $this->whenLoaded('attendances'),
            'salaries' => $this->whenLoaded('salaries'),
            
            // Timestamps
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
