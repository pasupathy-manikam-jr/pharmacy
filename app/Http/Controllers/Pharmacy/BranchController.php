<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use EInvoiceSdk\Codes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('branches/Index', [
            'branches' => Branch::query()->withCount('users')->orderBy('name')->get(),
            'states' => Codes::states(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $branch = Branch::query()->create($this->validated($request));
        AuditLog::record('branch.created', $branch);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name added.', ['name' => $branch->name])]);

        return back();
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $branch->update($this->validated($request));
        AuditLog::record('branch.updated', $branch, $branch->getChanges());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Branch saved.')]);

        return back();
    }

    /** Owners work across branches; everything they see and do follows the branch they switch to. */
    public function switch(Request $request, Branch $branch): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->update(['branch_id' => $branch->id]);
        AuditLog::record('branch.switched', $branch);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Now working in :name.', ['name' => $branch->name])]);

        return to_route('dashboard');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'licence_no' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:2'],
            'tin' => ['nullable', 'string', 'max:20'],
            'brn' => ['nullable', 'string', 'max:30'],
            'sst_no' => ['nullable', 'string', 'max:30'],
            'msic_code' => ['required', 'string', 'max:10'],
        ]);
    }
}
