<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Account;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function getAllInvoices(array $filters = [])
    {
        $query = Invoice::with(['customer', 'order']);

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('invoice_code', 'like', "%{$s}%")
                  ->orWhereHas('customer', fn($cq) => $cq->where('name', 'like', "%{$s}%"));
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('invoice_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('invoice_date', '<=', $filters['to_date']);
        }

        $query->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_order'] ?? 'desc');

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function getInvoiceById(int $id): Invoice
    {
        return Invoice::with(['customer', 'order.items.product', 'payments'])->findOrFail($id);
    }

    public function createFromOrder(int $orderId, array $extra = []): Invoice
    {
        return DB::transaction(function () use ($orderId, $extra) {
            $order = Order::with('items')->findOrFail($orderId);

            if ($order->status !== 'completed') {
                throw new \Exception('Chỉ có thể tạo hóa đơn từ đơn hàng đã hoàn thành.');
            }

            // Check if invoice already exists
            if (Invoice::where('order_id', $orderId)->exists()) {
                throw new \Exception('Đơn hàng này đã có hóa đơn.');
            }

            $invoiceCode = 'INV-' . date('Ymd') . '-' . str_pad(
                Invoice::whereDate('created_at', today())->count() + 1, 4, '0', STR_PAD_LEFT
            );

            $subtotal  = $order->total_amount;
            $taxRate   = $extra['tax_rate'] ?? 0;
            $taxAmount = round($subtotal * $taxRate / 100, 2);
            $total     = $subtotal + $taxAmount;

            $invoice = Invoice::create([
                'invoice_code' => $invoiceCode,
                'order_id'     => $order->id,
                'customer_id'  => $order->customer_id,
                'invoice_date' => now()->toDateString(),
                'due_date'     => $extra['due_date'] ?? now()->addDays(30)->toDateString(),
                'subtotal'     => $subtotal,
                'tax_rate'     => $taxRate,
                'tax_amount'   => $taxAmount,
                'total_amount' => $total,
                'status'       => 'draft',
                'notes'        => $extra['notes'] ?? null,
            ]);

            // Create account receivable
            Account::create([
                'type'           => 'receivable',
                'contact_type'   => 'App\\Models\\Customer',
                'contact_id'     => $order->customer_id,
                'reference_type' => 'App\\Models\\Invoice',
                'reference_id'   => $invoice->id,
                'total_amount'   => $total,
                'paid_amount'    => 0,
                'balance'        => $total,
                'due_date'       => $invoice->due_date,
                'status'         => 'open',
            ]);

            return $invoice->load(['customer', 'order']);
        });
    }

    public function updateInvoice(int $id, array $data): Invoice
    {
        $invoice = Invoice::findOrFail($id);

        if (!in_array($invoice->status, ['draft', 'sent'])) {
            throw new \Exception('Không thể chỉnh sửa hóa đơn ở trạng thái này.');
        }

        $invoice->update($data);

        // Sync account if amount changed
        if (isset($data['total_amount']) || isset($data['tax_rate'])) {
            $account = $invoice->account;
            if ($account) {
                $account->update([
                    'total_amount' => $invoice->total_amount,
                    'balance'      => $invoice->total_amount - $account->paid_amount,
                ]);
            }
        }

        return $invoice->fresh(['customer', 'order']);
    }

    public function sendInvoice(int $id): Invoice
    {
        $invoice = Invoice::findOrFail($id);
        if ($invoice->status !== 'draft') {
            throw new \Exception('Chỉ có thể gửi hóa đơn ở trạng thái Nháp.');
        }
        $invoice->update(['status' => 'sent']);
        return $invoice->fresh(['customer', 'order']);
    }

    public function cancelInvoice(int $id): Invoice
    {
        $invoice = Invoice::findOrFail($id);
        if ($invoice->status === 'paid') {
            throw new \Exception('Không thể hủy hóa đơn đã thanh toán.');
        }
        $invoice->update(['status' => 'cancelled']);

        // Cancel account
        $account = $invoice->account;
        if ($account) {
            $account->update(['status' => 'paid', 'balance' => 0]);
        }

        return $invoice->fresh(['customer', 'order']);
    }

    public function deleteInvoice(int $id): void
    {
        $invoice = Invoice::findOrFail($id);
        if ($invoice->status !== 'draft') {
            throw new \Exception('Chỉ có thể xóa hóa đơn ở trạng thái Nháp.');
        }
        $invoice->account?->delete();
        $invoice->delete();
    }

    public function getStatistics(array $filters = []): array
    {
        $query = Invoice::query();
        if (!empty($filters['from_date'])) $query->whereDate('invoice_date', '>=', $filters['from_date']);
        if (!empty($filters['to_date'])) $query->whereDate('invoice_date', '<=', $filters['to_date']);

        return [
            'total'        => $query->count(),
            'draft'        => (clone $query)->where('status', 'draft')->count(),
            'sent'         => (clone $query)->where('status', 'sent')->count(),
            'paid'         => (clone $query)->where('status', 'paid')->count(),
            'overdue'      => (clone $query)->where('status', 'overdue')->count(),
            'total_amount' => (clone $query)->whereIn('status', ['sent', 'paid', 'partial'])->sum('total_amount'),
            'paid_amount'  => (clone $query)->where('status', 'paid')->sum('total_amount'),
        ];
    }
}
