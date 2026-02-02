<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'email' => $this->email,
            'status' => $this->status,
            'status_label' => $this->status ? 'Active' : 'Inactive',
            'email_verified' => !is_null($this->email_verified_at),
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'last_login_at' => $this->last_login_at?->toISOString(),
            'last_login_human' => $this->last_login_at?->diffForHumans(),

            // conditional Relationships
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'employee' => new EmployeeResource($this->whenLoaded('employee')),

            // conditional attributes
            'permissions' => $this->when($request->user()?->hasRole('Super Admin'), function () {
                return $this->roles->flatMap->permissions->pluck('name')->unique()->values();
            }),

            // Timestamps
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'created_at_human' => $this->created_at->diffForHumans(),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'version' => '1.0',
                'timestamp' => now()->toISOString(),
            ],
        ];
    }
}
