<?php

namespace App\Http\Controllers\Pharmacy;

use App\Actions\Pharmacy\ReceiveGoods;
use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Support\PerPage;
use App\Support\Sort;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GoodsReceiptController extends Controller
{
    public function index(Request $request): Response
    {
        $query = GoodsReceipt::query()->select('goods_receipts.*')->join('suppliers', 'suppliers.id', '=', 'goods_receipts.supplier_id');
        $sort = Sort::apply($query, ['received_on' => 'goods_receipts.received_on', 'supplier' => 'suppliers.name', 'invoice_no' => 'goods_receipts.invoice_no', 'total_sen' => 'goods_receipts.total_sen', 'payment_status' => 'goods_receipts.payment_status'], 'received_on', 'desc', 'goods_receipts.id');

        return Inertia::render('receipts/Index', [
            'sort' => $sort,
            'receipts' => $query
                ->where('goods_receipts.branch_id', $request->user()?->branch_id)
                ->with('supplier:id,name')
                ->withCount('lines')
                ->paginate(PerPage::get())
                ->withQueryString(),
        ]);
    }

    public function create(Request $request): Response
    {
        $order = $request->integer('purchase_order')
            ? PurchaseOrder::query()->where('branch_id', $request->user()?->branch_id)->where('status', 'ordered')->with('lines')->find($request->integer('purchase_order'))
            : null;

        return Inertia::render('receipts/Create', [
            'order' => $order ? [
                'id' => $order->id,
                'number' => $order->number,
                'supplier_id' => $order->supplier_id,
                'lines' => $order->lines->map(fn ($l) => ['product_id' => $l->product_id, 'qty' => $l->qty, 'cost_sen' => $l->cost_sen])->values(),
            ] : null,
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'strength', 'barcode']),
        ]);
    }

    public function store(Request $request, ReceiveGoods $receive): RedirectResponse
    {
        /** @var array{supplier_id: int, purchase_order_id?: int|null, invoice_no?: string|null, received_on: string, payment_status: string, lines: list<array{product_id: int, batch_no: string, expiry_date: string, qty: int, cost_sen: int}>} $data */
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'invoice_no' => ['nullable', 'string', 'max:100'],
            'received_on' => ['required', 'date'],
            'payment_status' => ['required', 'in:paid,partial,pending'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.batch_no' => ['required', 'string', 'max:100'],
            'lines.*.expiry_date' => ['required', 'date_format:Y-m-d', 'after:today'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.cost_sen' => ['required', 'integer', 'min:0'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $receive->handle($user, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stock received.']);

        return to_route('receipts.index');
    }
}
