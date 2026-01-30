<?php

namespace App\Services;

use App\Models\Department;

class DepartmentService
{
    public function getAllDepartments(array $filters = [])
    {
        $query = Department::query();

        if (!empty($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%");
        }

        return $query->withCount('employees')
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getDepartmentById(int $id)
    {
        return Department::with('employees')->findOrFail($id);
    }

    public function createDepartment(array $data): Department
    {
        return Department::create($data);
    }

    public function updateDepartment(int $id, array $data): Department
    {
        $department = Department::findOrFail($id);
        $department->update($data);
        return $department->fresh();
    }

    public function deleteDepartment(int $id): bool
    {
        $department = Department::findOrFail($id);

        if ($department->employees()->exists()) {
            throw new \Exception('Không thể xóa phòng ban còn nhân viên!');
        }

        return $department->delete();
    }

    public function getDepartmentStatistics(): array
    {
        return [
            'total_departments' => Department::count(),
            'departments' => Department::withCount('employees')
                ->get()
                ->map(fn($d) => [
                    'name' => $d->name,
                    'employees_count' => $d->employees_count,
                ]),
        ];
    }
}
