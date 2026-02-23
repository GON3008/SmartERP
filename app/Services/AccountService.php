<?php

namespace App\Services;

use App\Models\Account;
use Illuminate\Support\Facades\DB;

class AccountService
{
    public function getAllAccounts(array $filters = [])
    {
        $query = Account::with(['contact', 'reference']);

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->whereHasMorph('contact', ['App\\Models\\Customer', 'App\\Models\\Supplier'], function ($mq) use ($s) {
                    $mq->where('name', 'like', "%{$s}%");
                });
            });
        }

        if (!empty($filters['overdue_only'])) {
            $query->where('status', '!=', 'paid')
                  ->whereNotNull('due_date')
                  ->whereDate('due_date', '<', now());
        }

        $query->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_order'] ?? 'desc');

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function getAccountById(int $id): Account
    {
        return Account::with(['contact', 'reference'])->findOrFail($id);
    }

    public function getSummary(): array
    {
        return [
            'receivable' => [
                'total'   => Account::where('type', 'receivable')->sum('total_amount'),
                'paid'    => Account::where('type', 'receivable')->sum('paid_amount'),
                'balance' => Account::where('type', 'receivable')->sum('balance'),
                'count'   => Account::where('type', 'receivable')->where('status', '!=', 'paid')->count(),
            ],
            'payable' => [
                'total'   => Account::where('type', 'payable')->sum('total_amount'),
                'paid'    => Account::where('type', 'payable')->sum('paid_amount'),
                'balance' => Account::where('type', 'payable')->sum('balance'),
                'count'   => Account::where('type', 'payable')->where('status', '!=', 'paid')->count(),
            ],
        ];
    }

    public function getAgingReport(string $type = 'receivable'): array
    {
        $accounts = Account::where('type', $type)
            ->where('status', '!=', 'paid')
            ->whereNotNull('due_date')
            ->get();

        $aging = [
            'current'  => 0, // not yet due
            '1_30'     => 0,
            '31_60'    => 0,
            '61_90'    => 0,
            'over_90'  => 0,
        ];

        $now = now();
        foreach ($accounts as $acc) {
            $overdueDays = $now->diffInDays($acc->due_date, false);

            if ($overdueDays >= 0) {
                $aging['current'] += $acc->balance;
            } elseif ($overdueDays >= -30) {
                $aging['1_30'] += $acc->balance;
            } elseif ($overdueDays >= -60) {
                $aging['31_60'] += $acc->balance;
            } elseif ($overdueDays >= -90) {
                $aging['61_90'] += $acc->balance;
            } else {
                $aging['over_90'] += $acc->balance;
            }
        }

        return $aging;
    }

    public function checkOverdue(): int
    {
        return Account::where('status', '!=', 'paid')
            ->where('status', '!=', 'overdue')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now())
            ->update(['status' => 'overdue']);
    }
}
