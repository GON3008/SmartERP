<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InvoiceService;
use App\Http\Resources\InvoiceResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InvoiceController extends Controller
{
    public function __construct(protected InvoiceService $invoiceService) {}

    public function index(Request $request): JsonResponse
    {
        $filters  = $request->only(['search', 'status', 'customer_id', 'from_date', 'to_date', 'sort_by', 'sort_order', 'per_page']);
        $invoices = $this->invoiceService->getAllInvoices($filters);

        return response()->json([
            'success' => true,
            'data'    => InvoiceResource::collection($invoices),
            'meta'    => [
                'total'        => $invoices->total(),
                'per_page'     => $invoices->perPage(),
                'current_page' => $invoices->currentPage(),
                'last_page'    => $invoices->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'due_date' => 'nullable|date',
            'notes'    => 'nullable|string|max:1000',
        ]);

        try {
            $invoice = $this->invoiceService->createFromOrder(
                $request->order_id,
                $request->only(['tax_rate', 'due_date', 'notes'])
            );
            return response()->json([
                'success' => true,
                'message' => 'Tạo hóa đơn thành công!',
                'data'    => new InvoiceResource($invoice),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function show(int $id): JsonResponse
    {
        $invoice = $this->invoiceService->getInvoiceById($id);
        return response()->json(['success' => true, 'data' => new InvoiceResource($invoice)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'due_date'  => 'nullable|date',
            'tax_rate'  => 'nullable|numeric|min:0|max:100',
            'notes'     => 'nullable|string|max:1000',
        ]);

        try {
            $invoice = $this->invoiceService->updateInvoice($id, $request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Cập nhật hóa đơn thành công!',
                'data'    => new InvoiceResource($invoice),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function send(int $id): JsonResponse
    {
        try {
            $invoice = $this->invoiceService->sendInvoice($id);
            return response()->json([
                'success' => true,
                'message' => 'Gửi hóa đơn thành công!',
                'data'    => new InvoiceResource($invoice),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function cancel(int $id): JsonResponse
    {
        try {
            $invoice = $this->invoiceService->cancelInvoice($id);
            return response()->json([
                'success' => true,
                'message' => 'Hủy hóa đơn thành công!',
                'data'    => new InvoiceResource($invoice),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->invoiceService->deleteInvoice($id);
            return response()->json(['success' => true, 'message' => 'Xóa hóa đơn thành công!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function statistics(Request $request): JsonResponse
    {
        $filters = $request->only(['from_date', 'to_date']);
        return response()->json([
            'success' => true,
            'data'    => $this->invoiceService->getStatistics($filters),
        ]);
    }
}
