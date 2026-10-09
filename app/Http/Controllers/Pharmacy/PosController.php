<?php

namespace App\Http\Controllers\Pharmacy;

use App\Actions\Pharmacy\CompleteSale;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function index(Request $request): Response
    {
        $branchId = $request->user()?->branch_id;

        // ponytail: whole active catalogue sent to the page for instant search; switch to a search endpoint past ~5k SKUs.
        $products = DB::table('products')
            ->leftJoin('batches', fn ($j) => $j->on('batches.product_id', '=', 'products.id')->whereDate('batches.expiry_date', '>', today()))
            ->leftJoin('stock_levels', fn ($j) => $j->on('stock_levels.batch_id', '=', 'batches.id')->where('stock_levels.branch_id', $branchId))
            ->where('products.is_active', true)
            ->groupBy('products.id', 'products.name', 'products.generic_name', 'products.strength', 'products.barcode', 'products.price_sen', 'products.tax_rate_bp', 'products.poison_group', 'products.image_path')
            ->orderBy('products.name')
            ->get([
                'products.id', 'products.name', 'products.generic_name', 'products.strength', 'products.barcode',
                'products.price_sen', 'products.tax_rate_bp', 'products.poison_group', 'products.image_path',
                DB::raw('COALESCE(SUM(stock_levels.qty), 0) as on_hand'),
            ])
            ->map(fn ($p) => [...(array) $p, 'image_url' => Product::urlFor($p->image_path)]);

        /** @var User $user */
        $user = $request->user();

        // Prescriptions that still have dispenses left, for refills at the counter.
        $refillable = Prescription::query()
            ->where('branch_id', $branchId)
            ->withCount(['sales' => fn ($q) => $q->where('status', '!=', 'refunded')])
            ->latest('id')
            ->limit(500)
            ->get()
            ->filter(fn (Prescription $p) => $p->sales_count < 1 + $p->refills_allowed)
            ->map(fn (Prescription $p) => [
                'id' => $p->id,
                'customer_id' => $p->customer_id,
                'label' => "{$p->prescriber_name}, {$p->issued_on->format('d M Y')}",
                'remaining' => 1 + $p->refills_allowed - $p->sales_count,
            ])
            ->values();

        return Inertia::render('pos/Index', [
            'products' => $products,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'ic_no', 'allergies']),
            'prescriptions' => $refillable,
            'isPharmacist' => $user->isPharmacist(),
            'shift' => Shift::openFor($user)?->only(['id', 'opened_at']),
        ]);
    }

    public function store(Request $request, CompleteSale $completeSale): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer.name' => ['nullable', 'string', 'max:255'],
            'customer.ic_no' => ['nullable', 'string', 'max:20'],
            'customer.address' => ['nullable', 'string', 'max:255'],
            'prescription_id' => ['nullable', 'exists:prescriptions,id'],
            'prescription' => ['nullable', 'array'],
            'prescription.refills_allowed' => ['nullable', 'integer', 'min:0', 'max:12'],
            'prescription.prescriber_name' => ['nullable', 'string', 'max:255'],
            'prescription.prescriber_reg_no' => ['nullable', 'string', 'max:50'],
            'prescription.clinic' => ['nullable', 'string', 'max:255'],
            'prescription.diagnosis' => ['nullable', 'string', 'max:255'],
            'prescription.issued_on' => ['required_with:prescription.prescriber_name', 'nullable', 'date', 'before_or_equal:today'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'distinct', 'exists:products,id'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.dosage' => ['nullable', 'string', 'max:255'],
            'discount_sen' => ['nullable', 'integer', 'min:0'],
            'payment_method' => ['required', Rule::in(Sale::PAYMENT_METHODS)],
            'tendered_sen' => ['nullable', 'integer', 'min:0'],
        ]);

        if (empty($data['prescription']['prescriber_name'])) {
            unset($data['prescription']);
        }

        /** @var User $user */
        $user = $request->user();
        $sale = $completeSale->handle($user, $data);

        return to_route('sales.show', $sale);
    }
}
