<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Support\PerPage;
use App\Support\Sort;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PrescriptionController extends Controller
{
    public function index(Request $request): Response
    {
        $search = (string) $request->string('search');

        $query = Prescription::query()->select('prescriptions.*')->join('customers', 'customers.id', '=', 'prescriptions.customer_id');
        $sort = Sort::apply($query, ['issued_on' => 'prescriptions.issued_on', 'patient' => 'customers.name', 'prescriber' => 'prescriptions.prescriber_name', 'diagnosis' => 'prescriptions.diagnosis'], 'issued_on', 'desc', 'prescriptions.id');

        return Inertia::render('prescriptions/Index', [
            'sort' => $sort,
            'prescriptions' => $query
                ->where('prescriptions.branch_id', $request->user()?->branch_id)
                ->when($search, fn ($q) => $q->where(fn ($q) => $q
                    ->where('prescriptions.prescriber_name', 'like', "%$search%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$search%")->orWhere('ic_no', 'like', "$search%"))))
                ->with('customer:id,name,ic_no')
                ->withCount(['sales' => fn ($q) => $q->where('status', '!=', 'refunded')])
                ->paginate(PerPage::get())
                ->withQueryString(),
            'search' => $search,
        ]);
    }
}
