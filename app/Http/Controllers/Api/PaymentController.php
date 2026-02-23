<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use App\Http\Resources\PaymentResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $paymentService) {}

    public function index(Request $request): JsonResponse
    {
        $filters  = $request->only(['search', 'payment_method', 'payable_type', 'from_date', 'to_date', 'sort_by', 'sort_order', 'per_page']);
        $payments = $this->paymentService->getAllPayments($filters);

        return response()->json([
            'success' => true,
            'data'    => PaymentResource::collection($payments),
            'meta'    => [
                'total'        => $payments->total(),
                'per_page'     => $payments->perPage(),
                'current_page' => $payments->currentPage(),
                'last_page'    => $payments->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'payable_type'   => 'required|in:App\\Models\\Invoice,App\\Models\\PurchaseOrder',
            'payable_id'     => 'required|integer',
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|in:cash,bank_transfer,card,other',
            'payment_date'   => 'nullable|date',
            'reference'      => 'nullable|string|max:255',
            'notes'          => 'nullable|string|max:1000',
        ]);

        try {
            $payment = $this->paymentService->createPayment($request->all());
            return response()->json([
                'success' => true,
                'message' => 'Ghi nhận thanh toán thành công!',
                'data'    => new PaymentResource($payment),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function show(int $id): JsonResponse
    {
        $payment = $this->paymentService->getPaymentById($id);
        return response()->json(['success' => true, 'data' => new PaymentResource($payment)]);
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->paymentService->deletePayment($id);
            return response()->json(['success' => true, 'message' => 'Xóa thanh toán thành công!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function statistics(Request $request): JsonResponse
    {
        $filters = $request->only(['from_date', 'to_date']);
        return response()->json([
            'success' => true,
            'data'    => $this->paymentService->getStatistics($filters),
        ]);
    }
}
