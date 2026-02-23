<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Account;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function getAllPayments(array $filters = [])
    {
        $query = Payment::with('payable');

        if (!empty($filters['search'])) {
            $query->where('payment_code', 'like', "%{$filters['search']}%");
        }

        if (!empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (!empty($filters['payable_type'])) {
            $query->where('payable_type', $filters['payable_type']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('payment_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('payment_date', '<=', $filters['to_date']);
        }

        $query->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_order'] ?? 'desc');

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function getPaymentById(int $id): Payment
    {
        return Payment::with('payable')->findOrFail($id);
    }

    public function createPayment(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            $paymentCode = 'PAY-' . date('Ymd') . '-' . str_pad(
                Payment::whereDate('created_at', today())->count() + 1, 4, '0', STR_PAD_LEFT
            );

            $payment = Payment::create([
                'payment_code'   => $paymentCode,
                'payable_type'   => $data['payable_type'],
                'payable_id'     => $data['payable_id'],
                'amount'         => $data['amount'],
                'payment_method' => $data['payment_method'] ?? 'cash',
                'payment_date'   => $data['payment_date'] ?? now()->toDateString(),
                'reference'      => $data['reference'] ?? null,
                'notes'          => $data['notes'] ?? null,
            ]);

            // Update the related document status and account
            $this->updatePayableStatus($data['payable_type'], $data['payable_id']);

            return $payment->load('payable');
        });
    }

    private function updatePayableStatus(string $type, int $id): void
    {
        if ($type === 'App\\Models\\Invoice') {
            $invoice     = Invoice::findOrFail($id);
            $totalPaid   = $invoice->payments()->sum('amount');
            $totalAmount = $invoice->total_amount;

            if ($totalPaid >= $totalAmount) {
                $invoice->update(['status' => 'paid']);
            } elseif ($totalPaid > 0) {
                $invoice->update(['status' => 'partial']);
            }

            // Update AR account
            $account = Account::where('reference_type', 'App\\Models\\Invoice')
                ->where('reference_id', $id)->first();
            if ($account) {
                $balance = max(0, $totalAmount - $totalPaid);
                $account->update([
                    'paid_amount' => $totalPaid,
                    'balance'     => $balance,
                    'status'      => $balance <= 0 ? 'paid' : ($totalPaid > 0 ? 'partial' : 'open'),
                ]);
            }
        } elseif ($type === 'App\\Models\\PurchaseOrder') {
            $po          = PurchaseOrder::findOrFail($id);
            $totalPaid   = $po->payments()->sum('amount');

            // Update AP account
            $account = Account::where('reference_type', 'App\\Models\\PurchaseOrder')
                ->where('reference_id', $id)->first();
            if ($account) {
                $balance = max(0, $account->total_amount - $totalPaid);
                $account->update([
                    'paid_amount' => $totalPaid,
                    'balance'     => $balance,
                    'status'      => $balance <= 0 ? 'paid' : ($totalPaid > 0 ? 'partial' : 'open'),
                ]);
            }
        }
    }

    public function deletePayment(int $id): void
    {
        $payment = Payment::findOrFail($id);
        $type    = $payment->payable_type;
        $pid     = $payment->payable_id;

        $payment->delete();

        // Re-sync status
        $this->updatePayableStatus($type, $pid);
    }

    public function getStatistics(array $filters = []): array
    {
        $query = Payment::query();
        if (!empty($filters['from_date'])) $query->whereDate('payment_date', '>=', $filters['from_date']);
        if (!empty($filters['to_date'])) $query->whereDate('payment_date', '<=', $filters['to_date']);

        return [
            'total_payments' => $query->count(),
            'total_amount'   => (clone $query)->sum('amount'),
            'by_method'      => [
                'cash'          => (clone $query)->where('payment_method', 'cash')->sum('amount'),
                'bank_transfer' => (clone $query)->where('payment_method', 'bank_transfer')->sum('amount'),
                'card'          => (clone $query)->where('payment_method', 'card')->sum('amount'),
                'other'         => (clone $query)->where('payment_method', 'other')->sum('amount'),
            ],
        ];
    }
}
