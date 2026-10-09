<?php

namespace App\Http\Controllers\Pharmacy;

use App\Actions\Pharmacy\RefundSale;
use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\User;
use App\Support\EInvoiceSummary;
use App\Support\PerPage;
use App\Support\Sort;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function index(Request $request): Response
    {
        $search = (string) $request->string('search');

        $query = Sale::query()->select('sales.*')->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')->join('users', 'users.id', '=', 'sales.user_id');
        $sort = Sort::apply($query, ['number' => 'sales.number', 'created_at' => 'sales.created_at', 'customer' => 'customers.name', 'cashier' => 'users.name', 'payment_method' => 'sales.payment_method', 'total_sen' => 'sales.total_sen'], 'created_at', 'desc', 'sales.id');

        return Inertia::render('sales/Index', [
            'sort' => $sort,
            'sales' => $query
                ->where('sales.branch_id', $request->user()?->branch_id)
                ->when($search, fn ($q) => $q->where('sales.number', 'like', "%$search%"))
                ->with(['customer:id,name', 'user:id,name'])
                ->paginate(PerPage::get())
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function show(Request $request, Sale $sale): Response
    {
        abort_unless($sale->branch_id === $request->user()?->branch_id, 404);

        $sale->load(['lines.product:id,name,strength,unit,poison_group', 'lines.batch:id,batch_no,expiry_date', 'customer', 'prescription', 'user:id,name', 'branch', 'refunds.user:id,name', 'refunds.lines']);

        return Inertia::render('sales/Show', [
            'sale' => $sale,
            'lineNet' => $sale->lineNetTotals(),
            'canRefund' => $request->user()->hasAnyRole(['owner', 'pharmacist']),
            'einvoice' => EInvoiceSummary::of($sale),
            'refundEinvoices' => $sale->refunds->mapWithKeys(fn (Refund $r) => [$r->id => EInvoiceSummary::of($r)]),
        ]);
    }

    public function refund(Request $request, Sale $sale, RefundSale $refund): RedirectResponse
    {
        abort_unless($sale->branch_id === $request->user()?->branch_id, 404);

        $data = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*' => ['integer', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();
        /** @var array<int, int> $lines */
        $lines = array_map('intval', $data['lines']);
        $result = $refund->handle($user, $sale, $lines, $data['reason']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Refunded :number.', ['number' => $result->number])]);

        return to_route('sales.show', $sale);
    }
}
