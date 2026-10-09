<?php

namespace App\Http\Controllers\Pharmacy;

use App\Actions\Pharmacy\AdjustStock;
use App\Actions\Pharmacy\TransferStock;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\PerPage;
use App\Support\Sort;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function index(Request $request): Response
    {
        $search = (string) $request->string('search');
        $query = StockLevel::query()
            ->select('stock_levels.*')
            ->join('batches', 'batches.id', '=', 'stock_levels.batch_id')
            ->join('products', 'products.id', '=', 'batches.product_id');
        $sort = Sort::apply($query, ['product' => 'products.name', 'batch_no' => 'batches.batch_no', 'expiry_date' => 'batches.expiry_date', 'qty' => 'stock_levels.qty', 'cost_sen' => 'batches.cost_sen', 'value_sen' => '(stock_levels.qty * batches.cost_sen)'], 'product');

        return Inertia::render('stock/Index', [
            'sort' => $sort,
            'levels' => $query
                ->where('stock_levels.branch_id', $request->user()?->branch_id)
                ->where('stock_levels.qty', '!=', 0)
                ->when($search, fn ($q) => $q->where(fn ($q) => $q->where('products.name', 'like', "%$search%")->orWhere('batches.batch_no', 'like', "$search%")))
                ->orderBy('batches.expiry_date')
                ->with('batch.product:id,name,strength,unit')
                ->paginate(PerPage::get())
                ->withQueryString()
                ->through(fn (StockLevel $l) => [
                    'id' => $l->id,
                    'batch_id' => $l->batch_id,
                    'product' => trim($l->batch->product->name.' '.$l->batch->product->strength),
                    'unit' => $l->batch->product->unit,
                    'batch_no' => $l->batch->batch_no,
                    'expiry_date' => $l->batch->expiry_date->toDateString(),
                    'days_left' => (int) today()->diffInDays($l->batch->expiry_date, false),
                    'qty' => $l->qty,
                    'cost_sen' => $l->batch->cost_sen,
                ]),
            'search' => $search,
            'reasons' => AdjustStock::REASONS,
            'canAdjust' => $request->user()->hasAnyRole(['owner', 'pharmacist']),
        ]);
    }

    public function adjust(Request $request, Batch $batch, AdjustStock $adjust): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(AdjustStock::REASONS))],
            'counted' => ['required_if:reason,count', 'nullable', 'integer', 'min:0'],
            'qty' => ['exclude_if:reason,count', 'required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $onHand = (int) StockLevel::query()->where('branch_id', $user->branch_id)->where('batch_id', $batch->id)->value('qty');
        $delta = $data['reason'] === 'count' ? (int) $data['counted'] - $onHand : -(int) $data['qty'];

        $adjust->handle($user, $batch, $delta, $data['reason'], $data['note'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => $delta === 0 ? 'Count matches, nothing changed.' : 'Stock adjusted.']);

        return back();
    }

    public function movements(Request $request): Response
    {
        $type = (string) $request->string('type');
        $query = StockMovement::query()
            ->select('stock_movements.*')
            ->join('batches', 'batches.id', '=', 'stock_movements.batch_id')
            ->join('products', 'products.id', '=', 'batches.product_id');
        $sort = Sort::apply($query, ['created_at' => 'stock_movements.created_at', 'product' => 'products.name', 'batch_no' => 'batches.batch_no', 'type' => 'stock_movements.type', 'qty_delta' => 'stock_movements.qty_delta', 'qty_after' => 'stock_movements.qty_after'], 'created_at', 'desc', 'stock_movements.id');

        return Inertia::render('stock/Movements', [
            'sort' => $sort,
            'movements' => $query
                ->where('stock_movements.branch_id', $request->user()?->branch_id)
                ->when($type, fn ($q) => $q->where('stock_movements.type', $type))
                ->with(['batch:id,batch_no,product_id', 'batch.product:id,name,strength'])
                ->paginate(PerPage::get())
                ->withQueryString(),
            'type' => $type,
        ]);
    }

    public function transfer(Request $request): Response
    {
        $branchId = $request->user()?->branch_id;

        return Inertia::render('stock/Transfer', [
            'branches' => Branch::query()->whereKeyNot($branchId)->orderBy('name')->get(['id', 'name']),
            'batches' => StockLevel::query()
                ->where('branch_id', $branchId)
                ->where('qty', '>', 0)
                ->with('batch.product:id,name,strength')
                ->get()
                ->map(fn (StockLevel $l) => [
                    'value' => $l->batch_id,
                    'label' => trim($l->batch->product->name.' '.$l->batch->product->strength).' · '.$l->batch->batch_no,
                    'hint' => "{$l->qty} left",
                    'qty' => $l->qty,
                ]),
        ]);
    }

    public function storeTransfer(Request $request, TransferStock $transfer): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var array{to_branch_id: int, note?: string|null, lines: list<array{batch_id: int, qty: int}>} $data */
        $data = $request->validate([
            'to_branch_id' => ['required', 'exists:branches,id'],
            'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.batch_id' => ['required', 'distinct', 'exists:batches,id'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
        ]);

        $transfer->handle($user, Branch::query()->findOrFail($data['to_branch_id']), $data['lines'], $data['note'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stock transferred.']);

        return to_route('stock.movements');
    }
}
