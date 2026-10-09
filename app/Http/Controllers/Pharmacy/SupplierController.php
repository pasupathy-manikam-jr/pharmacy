<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Support\Sort;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(): Response
    {
        $query = Supplier::query();
        $sort = Sort::apply($query, ['name' => 'name', 'tin' => 'tin', 'phone' => 'phone', 'email' => 'email'], 'name', tieBreaker: 'id');

        return Inertia::render('suppliers/Index', ['suppliers' => $query->get(), 'sort' => $sort]);
    }

    public function store(Request $request): RedirectResponse
    {
        Supplier::query()->create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tin' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ]));

        return back();
    }
}
