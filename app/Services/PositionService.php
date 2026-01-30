<?php

namespace App\Services;

use App\Models\Position;

class PositionService
{
    public function getAllPositions(array $filters = [])
    {
        $query = Position::query();

        if (!empty($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%");
        }

        return $query->withCount('employees')
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getPositionById(int $id)
    {
        return Position::with('employees')->findOrFail($id);
    }

    public function createPosition(array $data): Position
    {
        return Position::create($data);
    }

    public function updatePosition(int $id, array $data): Position
    {
        $position = Position::findOrFail($id);
        $position->update($data);
        return $position->fresh();
    }

    public function deletePosition(int $id): bool
    {
        $position = Position::findOrFail($id);

        if ($position->employees()->exists()) {
            throw new \Exception('Không thể xóa chức vụ còn nhân viên!');
        }

        return $position->delete();
    }

    public function getPositionStatistics(): array
    {
        return [
            'total_positions' => Position::count(),
            'positions' => Position::withCount('employees')
                ->get()
                ->map(fn($p) => [
                    'name' => $p->name,
                    'employees_count' => $p->employees_count,
                ]),
        ];
    }
}
