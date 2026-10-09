<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Prescription;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use App\Support\PerPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = (string) $request->string('search');

        return Inertia::render('customers/Index', [
            'customers' => Customer::query()
                ->when($search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%$search%")->orWhere('ic_no', 'like', "$search%")->orWhere('phone', 'like', "%$search%")))
                ->orderBy('name')
                ->paginate(PerPage::get())
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = Customer::query()->create($this->validated($request));

        return to_route('customers.show', $customer);
    }

    public function show(Customer $customer): Response
    {
        return Inertia::render('customers/Show', [
            'customer' => $customer,
            'balance_sen' => $customer->balanceSen(),
            'sales' => Sale::query()->where('customer_id', $customer->id)->latest('id')->limit(50)->get(['id', 'number', 'created_at', 'total_sen', 'payment_method', 'status']),
            'payments' => CustomerPayment::query()->where('customer_id', $customer->id)->with('user:id,name')->latest('id')->limit(50)->get(),
            'prescriptions' => Prescription::query()->where('customer_id', $customer->id)->withCount(['sales' => fn ($q) => $q->where('status', '!=', 'refunded')])->latest('id')->get(),
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Customer saved.']);

        return back();
    }

    public function payment(Request $request, Customer $customer): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'amount_sen' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'in:cash,card,ewallet,bank'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        if ($data['amount_sen'] > $customer->balanceSen()) {
            throw ValidationException::withMessages(['amount_sen' => 'That is more than the customer owes.']);
        }

        $shift = Shift::openFor($user);
        if ($data['method'] === 'cash' && ! $shift) {
            throw ValidationException::withMessages(['method' => 'Open a shift to take cash.']);
        }

        $payment = CustomerPayment::query()->create([...$data, 'customer_id' => $customer->id, 'branch_id' => $user->branch_id, 'shift_id' => $shift?->id, 'user_id' => $user->id]);
        AuditLog::record('customer.payment', $customer, ['amount_sen' => $payment->amount_sen, 'method' => $payment->method]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment recorded.']);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ic_no' => ['nullable', 'string', 'max:20'],
            'dob' => ['nullable', 'date', 'before_or_equal:today'],
            'sex' => ['nullable', 'in:M,F'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'citizenship' => ['nullable', 'string', 'max:50'],
            'allergies' => ['nullable', 'string', 'max:255'],
            'tin' => ['nullable', 'string', 'max:20'],
            'brn' => ['nullable', 'string', 'max:30'],
        ]);
    }
}
