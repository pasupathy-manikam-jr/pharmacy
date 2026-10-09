<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Shift;
use App\Models\User;
use App\Support\PerPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $current = Shift::openFor($user);

        return Inertia::render('shifts/Index', [
            'current' => $current ? [...$current->toArray(), 'summary' => $current->cashSummary()] : null,
            'history' => Shift::query()
                ->where('branch_id', $user->branch_id)
                ->when(! $user->hasAnyRole(['owner', 'pharmacist']), fn ($q) => $q->where('user_id', $user->id))
                ->whereNotNull('closed_at')
                ->with('user:id,name')
                ->latest('id')
                ->paginate(PerPage::get())
                ->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate(['opening_float_sen' => ['required', 'integer', 'min:0']]);

        if (Shift::openFor($user)) {
            throw ValidationException::withMessages(['opening_float_sen' => 'You already have an open shift.']);
        }

        Shift::query()->create(['branch_id' => $user->branch_id, 'user_id' => $user->id, 'opening_float_sen' => $data['opening_float_sen'], 'opened_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Shift opened.']);

        return back();
    }

    public function close(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'counted_cash_sen' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $shift = Shift::openFor($user);
        if (! $shift) {
            throw ValidationException::withMessages(['counted_cash_sen' => 'You have no open shift.']);
        }

        $expected = $shift->cashSummary()['expected'];
        $shift->update(['closed_at' => now(), 'expected_cash_sen' => $expected, 'counted_cash_sen' => $data['counted_cash_sen'], 'note' => $data['note'] ?? null]);

        AuditLog::record('shift.closed', $shift, ['expected_sen' => $expected, 'counted_sen' => $data['counted_cash_sen'], 'variance_sen' => $data['counted_cash_sen'] - $expected]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Shift closed.']);

        return to_route('shifts.index');
    }
}
