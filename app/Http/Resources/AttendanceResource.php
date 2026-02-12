<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
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
            'date' => $this->date,
            'check_in' => $this->check_in,
            'check_out' => $this->check_out,
            
            // Calculated fields
            'work_hours' => $this->when(
                $this->check_in && $this->check_out,
                function () {
                    $checkIn = \Carbon\Carbon::parse($this->check_in);
                    $checkOut = \Carbon\Carbon::parse($this->check_out);
                    return round($checkOut->diffInHours($checkIn, true), 2);
                }
            ),
            'is_late' => $this->when(
                $this->check_in,
                function () {
                    return \Carbon\Carbon::parse($this->check_in)->format('H:i') > '08:30';
                }
            ),
            'is_early_departure' => $this->when(
                $this->check_out,
                function () {
                    return \Carbon\Carbon::parse($this->check_out)->format('H:i') < '17:30';
                }
            ),
            
            // Relationships
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            
            // Timestamps
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
