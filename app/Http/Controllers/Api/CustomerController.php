<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CustomerService;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    protected $customerService;

    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }

    public function index(Request $request)
    {
        $filters = $request->only(['search', 'sort_by', 'sort_order', 'per_page']);
        $customers = $this->customerService->getAllCustomers($filters);
        return response()->json([
            'success' => true,
            'data' => CustomerResource::collection($customers),
        ]);
    }

    public function store(StoreCustomerRequest $request)
    {
        $customer = $this->customerService->createCustomer($request->validated());
        return response()->json([
            'success' => true,
            'message' => 'Tạo khách hàng thành công!',
            'data' => new CustomerResource($customer),
            ], 201);
    }

    public function show(int $id)
    {
        $customerShow = $this->customerService->getCustomerById($id);
        return response()->json([
            'success' => true,
            'data' => new CustomerResource($customerShow)]);
    }

    public function update(UpdateCustomerRequest $request, int $id)
    {
        $customer = $this->customerService->updateCustomer($id, $request->validated());
        return response()->json(['message' => 'Cập nhật thành công!', 'data' => $customer]);
    }

    public function destroy(int $id)
    {
        try {
            $this->customerService->deleteCustomer($id);
            return response()->json(['message' => 'Xóa khách hàng thành công!']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 422);
        }
    }

    public function orders(Request $request, int $id)
    {
        $filters = $request->only(['status', 'from_date', 'to_date', 'per_page']);
        return response()->json($this->customerService->getCustomerOrders($id, $filters));
    }

    public function statistics(int $id)
    {
        return response()->json(['data' => $this->customerService->getCustomerStatistics($id)]);
    }

    public function topCustomers(Request $request)
    {
        $limit = $request->get('limit', 10);
        $filters = $request->only(['from_date', 'to_date']);
        return response()->json(['data' => $this->customerService->getTopCustomers($limit, $filters)]);
    }
}
