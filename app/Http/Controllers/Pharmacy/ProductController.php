<?php

namespace App\Http\Controllers\Pharmacy;

use App\Enums\PoisonGroup;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Product;
use App\Support\PerPage;
use App\Support\Sort;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $search = (string) $request->string('search');
        $query = Product::query();
        $sort = Sort::apply($query, ['name' => 'name', 'generic_name' => 'generic_name', 'poison_group' => 'poison_group', 'barcode' => 'barcode', 'price_sen' => 'price_sen', 'reorder_level' => 'reorder_level'], 'name', tieBreaker: 'id');

        return Inertia::render('products/Index', [
            'sort' => $sort,
            'products' => $query
                ->when($search, fn ($q) => $q->where(fn ($q) => $q
                    ->where('name', 'like', "%$search%")
                    ->orWhere('generic_name', 'like', "%$search%")
                    ->orWhere('barcode', $search)))
                ->paginate(PerPage::get())
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('products/Form', ['product' => null, 'poisonGroups' => array_column(PoisonGroup::cases(), 'value')]);
    }

    public function store(Request $request): RedirectResponse
    {
        Product::query()->create($this->validated($request));

        return to_route('products.index');
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('products/Form', ['product' => $product, 'poisonGroups' => array_column(PoisonGroup::cases(), 'value')]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $product->update($this->validated($request, $product));

        if ($changes = array_intersect_key($product->getChanges(), array_flip(['price_sen', 'poison_group', 'tax_rate_bp']))) {
            AuditLog::record('product.updated', $product, $changes);
        }

        return to_route('products.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'generic_name' => ['nullable', 'string', 'max:255'],
            'strength' => ['nullable', 'string', 'max:100'],
            'form' => ['nullable', 'string', 'max:100'],
            'poison_group' => ['required', Rule::enum(PoisonGroup::class)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('products')->ignore($product)],
            'mal_reg_no' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:30'],
            'price_sen' => ['required', 'integer', 'min:0'],
            'tax_rate_bp' => ['required', 'integer', 'min:0', 'max:10000'],
            'reorder_level' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
    }
}
