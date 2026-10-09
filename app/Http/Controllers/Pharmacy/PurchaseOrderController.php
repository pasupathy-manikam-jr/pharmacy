<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('purchase-orders/Index', [
            'orders' => PurchaseOrder::query()
                ->where('branch_id', $request->user()?->branch_id)
                ->with('supplier:id,name')
                ->withCount('lines')
                ->select('purchase_orders.*')
                ->addSelect(['total_sen' => PurchaseOrderLine::query()->selectRaw('COALESCE(SUM(qty * cost_sen), 0)')->whereColumn('purchase_order_id', 'purchase_orders.id')])
                ->latest('id')
                ->paginate(25),
        ]);
    }

    public function create(Request $request): Response
    {
        $branchId = $request->user()?->branch_id;

        // Suggest everything at or below its reorder level.
        $suggested = DB::table('products')
            ->leftJoin('batches', 'batches.product_id', '=', 'products.id')
            ->leftJoin('stock_levels', fn ($j) => $j->on('stock_levels.batch_id', '=', 'batches.id')->where('stock_levels.branch_id', $branchId))
            ->where('products.is_active', true)
            ->where('products.reorder_level', '>', 0)
            ->groupBy('products.id', 'products.reorder_level')
            ->havingRaw('COALESCE(SUM(stock_levels.qty), 0) <= products.reorder_level')
            ->get(['products.id', 'products.reorder_level', DB::raw('COALESCE(SUM(stock_levels.qty), 0) as on_hand'), DB::raw('COALESCE(MAX(batches.cost_sen), 0) as last_cost')]);

        return Inertia::render('purchase-orders/Create', [
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'strength']),
            'suggested' => $suggested->map(fn ($r) => ['product_id' => $r->id, 'qty' => max(1, (int) $r->reorder_level * 2 - (int) $r->on_hand), 'cost_sen' => (int) $r->last_cost])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'expected_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'distinct', 'exists:products,id'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.cost_sen' => ['required', 'integer', 'min:0'],
        ]);

        $order = DB::transaction(function () use ($user, $data) {
            $order = PurchaseOrder::query()->create([
                'branch_id' => $user->branch_id,
                'supplier_id' => $data['supplier_id'],
                'number' => uniqid('tmp', true),
                'expected_on' => $data['expected_on'] ?? null,
                'note' => $data['note'] ?? null,
                'user_id' => $user->id,
            ]);
            $order->update(['number' => sprintf('PO%s-%05d', now()->format('ym'), $order->id)]);
            $order->lines()->createMany($data['lines']);

            return $order;
        });

        return to_route('purchase-orders.show', $order);
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        abort_unless($purchaseOrder->branch_id === $request->user()?->branch_id, 404);

        return Inertia::render('purchase-orders/Show', [
            'order' => $purchaseOrder->load(['supplier', 'branch', 'user:id,name', 'lines.product:id,name,strength,unit']),
        ]);
    }

    public function status(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($purchaseOrder->branch_id === $request->user()?->branch_id, 404);
        $status = $request->validate(['status' => ['required', 'in:ordered,cancelled']])['status'];

        $allowed = ['draft' => ['ordered', 'cancelled'], 'ordered' => ['cancelled']];
        if (! in_array($status, $allowed[$purchaseOrder->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "A {$purchaseOrder->status} order can’t be marked {$status}."]);
        }

        $purchaseOrder->update(['status' => $status]);
        AuditLog::record("purchase_order.$status", $purchaseOrder);

        return back();
    }
}
