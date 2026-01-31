<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function index()
    {
        return response()->json(['data' => $this->notificationService->getAllNotifications()]);
    }

    public function count()
    {
        return response()->json(['count' => $this->notificationService->getNotificationCount()]);
    }

    public function lowStock()
    {
        return response()->json(['data' => $this->notificationService->getLowStockNotifications()]);
    }

    public function pendingOrders()
    {
        return response()->json(['data' => $this->notificationService->getPendingOrderNotifications()]);
    }
}
