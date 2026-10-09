<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PrescriptionController extends Controller
{
    public function index(Request $request): Response
    {
        $search = (string) $request->string('search');

        return Inertia::render('prescriptions/Index', [
            'prescriptions' => Prescription::query()
                ->where('branch_id', $request->user()?->branch_id)
                ->when($search, fn ($q) => $q->where(fn ($q) => $q
                    ->where('prescriber_name', 'like', "%$search%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$search%")->orWhere('ic_no', 'like', "$search%"))))
                ->with('customer:id,name,ic_no')
                ->withCount(['sales' => fn ($q) => $q->where('status', '!=', 'refunded')])
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'search' => $search,
        ]);
    }
}
