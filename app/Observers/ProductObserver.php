<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class ProductObserver
{
    /**
     * Handle the Product "created" event.
     */
    public function created(Product $product): void
    {
        $this->logActivity('created', $product);
    }

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        $this->logActivity('updated', $product);
    }

    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        $this->logActivity('deleted', $product);
    }

    /**
     * Log activity to ActivityLog table
     */
    private function logActivity(string $action, Product $product): void
    {
        $description = $this->buildDescription($action, $product);

        ActivityLog::create([
            'user_id' => Auth::id() ?? null,
            'action' => $action,
            'table_name' => 'products',
            'record_id' => $product->id,
            'description' => $description,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * Build description based on action
     */
    private function buildDescription(string $action, Product $product): string
    {
        switch ($action) {
            case 'created':
                return "Created product: {$product->name} (SKU: {$product->sku})";

            case 'updated':
                $changes = $product->getChanges();
                unset($changes['updated_at']);

                if (empty($changes)) {
                    return "Updated product: {$product->name}";
                }

                $changedFields = implode(', ', array_keys($changes));
                return "Updated product: {$product->name} - Changed fields: {$changedFields}";

            case 'deleted':
                return "Deleted product: {$product->name} (SKU: {$product->sku})";

            default:
                return "Action {$action} on product: {$product->name}";
        }
    }
}
