<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReceiptService
{
    /**
     * Issue the receipt for an order, or hand back the one it already has.
     * Receipts are never re-issued — a reprint reproduces the original.
     */
    public function issue(Order $order, ?User $issuer = null): Receipt
    {
        if ($order->relationLoaded('receipt') && $order->receipt) {
            return $order->receipt;
        }

        if ($existing = $order->receipt()->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($order, $issuer) {
            $receipt = $order->receipt()->create([
                'receipt_number' => $this->nextNumber(),
                'issued_by' => $issuer?->id,
                'issued_at' => Carbon::now(),
                'print_count' => 0,
            ]);

            $order->setRelation('receipt', $receipt);

            return $receipt;
        });
    }

    /** Count a trip to the printer so reprints can be labelled as such. */
    public function markPrinted(Receipt $receipt): Receipt
    {
        $receipt->increment('print_count');

        return $receipt->refresh();
    }

    /**
     * Sequential document number, e.g. VD-000042. Allocated inside a
     * transaction with the row locked so two registers cannot collide.
     */
    private function nextNumber(): string
    {
        $prefix = config('void.receipt.prefix', 'VD');
        $padding = (int) config('void.receipt.padding', 6);

        $last = Receipt::query()
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('receipt_number');

        $sequence = 1;

        if ($last && preg_match('/(\d+)$/', $last, $matches) === 1) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%0'.$padding.'d', $prefix, $sequence);
    }
}
