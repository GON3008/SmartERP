<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AccountService;
use App\Http\Resources\AccountResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    public function __construct(protected AccountService $accountService) {}

    public function index(Request $request): JsonResponse
    {
        $filters  = $request->only(['type', 'status', 'search', 'overdue_only', 'sort_by', 'sort_order', 'per_page']);
        $accounts = $this->accountService->getAllAccounts($filters);

        return response()->json([
            'success' => true,
            'data'    => AccountResource::collection($accounts),
            'meta'    => [
                'total'        => $accounts->total(),
                'per_page'     => $accounts->perPage(),
                'current_page' => $accounts->currentPage(),
                'last_page'    => $accounts->lastPage(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $account = $this->accountService->getAccountById($id);
        return response()->json(['success' => true, 'data' => new AccountResource($account)]);
    }

    public function summary(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->accountService->getSummary(),
        ]);
    }

    public function aging(Request $request): JsonResponse
    {
        $type = $request->query('type', 'receivable');
        return response()->json([
            'success' => true,
            'data'    => $this->accountService->getAgingReport($type),
        ]);
    }

    public function checkOverdue(): JsonResponse
    {
        $count = $this->accountService->checkOverdue();
        return response()->json([
            'success' => true,
            'message' => "Đã cập nhật {$count} khoản công nợ quá hạn.",
        ]);
    }
}
