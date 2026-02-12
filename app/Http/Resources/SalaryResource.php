<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\EmployeeResource;

class SalaryResource extends JsonResource
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
            'employee_id' => $this->employee_id,
            'base_salary' => $this->base_salary,
            'allowance' => $this->allowance,
            'deduction' => $this->deduction,
            'total_salary' => $this->total_salary,
            'month' => $this->month,
            'year' => $this->year,

            // Formatted values
            'base_salary_formatted' => number_format($this->base_salary, 0, ',', '.') . ' VNĐ',
            'total_salary_formatted' => number_format($this->total_salary, 0, ',', '.') . ' VNĐ',
            'period' => sprintf('%02d/%d', $this->month, $this->year),

            // Relationships
            'employee' => new EmployeeResource($this->whenLoaded('employee')),

            // Timestamps
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
