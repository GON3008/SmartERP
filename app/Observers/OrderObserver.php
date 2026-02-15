<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        $this->logActivity('created', $order);
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        $this->logActivity('updated', $order);
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        $this->logActivity('deleted', $order);
    }

    /**
     * Log activity to ActivityLog table
     */
    private function logActivity(string $action, Order $order): void
    {
        $description = $this->buildDescription($action, $order);

        ActivityLog::create([
            'user_id' => Auth::id() ?? null,
            'action' => $action,
            'table_name' => 'orders',
            'record_id' => $order->id,
            'description' => $description,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * Build description based on action
     */
    private function buildDescription(string $action, Order $order): string
    {
        switch ($action) {
            case 'created':
                return "Created order #{$order->id} - Status: {$order->status} - Total: {$order->total_amount}";
            
            case 'updated':
                $changes = $order->getChanges();
                unset($changes['updated_at']);
                
                if (empty($changes)) {
                    return "Updated order #{$order->id}";
                }
                
                // Special handling for status changes
                if (isset($changes['status'])) {
                    $oldStatus = $order->getOriginal('status');
                    return "Order #{$order->id} status changed from {$oldStatus} to {$order->status}";
                }
                
                $changedFields = implode(', ', array_keys($changes));
                return "Updated order #{$order->id} - Changed fields: {$changedFields}";
            
            case 'deleted':
                return "Deleted order #{$order->id}";
            
            default:
                return "Action {$action} on order #{$order->id}";
        }
    }
}
