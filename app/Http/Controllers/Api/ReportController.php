<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function dashboard()
    {
        return response()->json($this->reportService->getDashboardStatistics());
    }

    public function sales(Request $request)
    {
        $period = $request->get('period', 'daily'); // daily, weekly, monthly, yearly
        $filters = $request->only(['from_date', 'to_date']);

        return response()->json($this->reportService->getSalesReport($period, $filters));
    }

    public function topProducts(Request $request)
    {
        $limit = $request->get('limit', 10);
        $filters = $request->only(['from_date', 'to_date']);

        return response()->json(['data' => $this->reportService->getTopSellingProductsReport($limit, $filters)]);
    }

    public function inventoryMovement(Request $request)
    {
        $filters = $request->only(['from_date', 'to_date']);
        return response()->json($this->reportService->getInventoryMovementReport($filters));
    }

    public function customers(Request $request)
    {
        $filters = $request->only(['min_orders', 'per_page']);
        return response()->json($this->reportService->getCustomerReport($filters));
    }

    public function productionEfficiency(Request $request)
    {
        $filters = $request->only(['from_date', 'to_date']);
        return response()->json($this->reportService->getProductionEfficiencyReport($filters));
    }

    public function financialSummary(Request $request)
    {
        $filters = $request->only(['from_date', 'to_date']);
        return response()->json($this->reportService->getFinancialSummary($filters));
    }
}
