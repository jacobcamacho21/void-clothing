<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\ReceiptService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Receipts. `show` renders the document; `print` is the same document in a
 * bare, print-triggering page for the counter printer.
 */
class ReceiptController extends Controller
{
    public function __construct(private readonly ReceiptService $receipts) {}

    public function show(Request $request, Order $order): View
    {
        $this->authorize('printReceipt', $order);

        $data = $this->payload($order, $request);

        // The register asks for the document on its own so it can drop the
        // real receipt into its modal. One template renders the receipt for
        // the screen, the printer and the register alike, so the copy the
        // cashier reads and the copy the customer takes home cannot differ.
        if ($request->boolean('fragment')) {
            return view('pos.partials.receipt-document', $data);
        }

        return view('pos.receipt', $data);
    }

    public function print(Request $request, Order $order): View
    {
        $this->authorize('printReceipt', $order);

        $data = $this->payload($order, $request);
        $this->receipts->markPrinted($data['receipt']);

        return view('pos.receipt-print', [...$data, 'autoPrint' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Order $order, Request $request): array
    {
        $order->load(['items', 'payment', 'cashier', 'customer', 'receipt']);

        // Orders that predate the receipt table (or online orders reaching the
        // counter for the first time) get their document issued on demand.
        $receipt = $order->receipt ?? $this->receipts->issue($order, $request->user());

        return [
            'order' => $order,
            'receipt' => $receipt,
            'terminal' => config('void.pos.terminal'),
        ];
    }
}
