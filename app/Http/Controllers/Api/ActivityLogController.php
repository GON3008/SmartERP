<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of activity logs
     */
    public function index(Request $request)
    {
        $query = ActivityLog::with('user');

        // Filter by table_name
        if ($request->has('table_name')) {
            $query->where('table_name', $request->table_name);
        }

        // Filter by user_id
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by action
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        // Filter by record_id
        if ($request->has('record_id')) {
            $query->where('record_id', $request->record_id);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($logs);
    }

    /**
     * Display activity logs for a specific record
     */
    public function show($tableName, $recordId)
    {
        $logs = ActivityLog::with('user')
            ->where('table_name', $tableName)
            ->where('record_id', $recordId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($logs);
    }

    /**
     * Get activity statistics
     */
    public function statistics()
    {
        $stats = [
            'total_logs' => ActivityLog::count(),
            'by_table' => ActivityLog::selectRaw('table_name, COUNT(*) as count')
                ->groupBy('table_name')
                ->get(),
            'by_action' => ActivityLog::selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->get(),
            'recent_activities' => ActivityLog::with('user')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(),
        ];

        return response()->json($stats);
    }
}
