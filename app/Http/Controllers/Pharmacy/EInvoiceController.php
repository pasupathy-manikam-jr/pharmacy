<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\ConsolidatedEinvoice;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\User;
use App\Support\EInvoiceSummary;
use EInvoiceSdk\Contracts\EInvoiceable;
use EInvoiceSdk\EInvoice;
use EInvoiceSdk\Enums\Environment;
use EInvoiceSdk\Exceptions\EInvoiceException;
use EInvoiceSdk\Models\EInvoiceDocument;
use EInvoiceSdk\Models\EInvoiceSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * LHDN MyInvois: individual e-invoices for customers who ask, refund notes against them, the monthly
 * consolidated e-invoice for walk-in sales, and the branch's MyInvois credentials.
 */
class EInvoiceController extends Controller
{
    public function __construct(private EInvoice $einvoice) {}

    public function index(Request $request): Response
    {
        /** @var Branch $branch */
        $branch = $request->user()?->branch;
        $settings = $branch->tin ? EInvoiceSetting::query()->where('tin', $branch->tin)->get(['id', 'environment', 'client_id', 'unsigned', 'active']) : collect();

        $months = collect(range(1, 12))->map(function (int $ago) use ($branch) {
            $period = now()->startOfMonth()->subMonthsNoOverflow($ago)->format('Y-m');
            $batch = ConsolidatedEinvoice::query()->where('branch_id', $branch->id)->where('period', $period)->first();

            return [
                'period' => $period,
                'batch' => $batch,
                'einvoice' => $batch ? EInvoiceSummary::of($batch) : null,
                'pending_sales' => $batch ? null : ConsolidatedEinvoice::salesFor($branch->id, $period)->count(),
            ];
        });

        return Inertia::render('einvoice/Index', [
            'branch' => $branch->only(['id', 'name', 'tin', 'brn', 'company_name']),
            'settings' => $settings,
            'months' => $months,
            'thisMonth' => ['period' => now()->format('Y-m'), 'sales' => ConsolidatedEinvoice::salesFor($branch->id, now()->format('Y-m'))->count()],
            'recent' => EInvoiceDocument::query()->latest('id')->limit(20)->get(['id', 'number', 'type', 'status', 'environment', 'uuid', 'created_at']),
        ]);
    }

    public function submitSale(Request $request, Sale $sale): RedirectResponse
    {
        abort_unless($sale->branch_id === $request->user()?->branch_id, 404);

        return $this->send($sale);
    }

    public function submitRefund(Request $request, Refund $refund): RedirectResponse
    {
        abort_unless($refund->branch_id === $request->user()?->branch_id, 404);

        return $this->send($refund);
    }

    public function consolidate(Request $request): RedirectResponse
    {
        $branchId = (int) $request->user()?->branch_id;
        $period = $request->validate(['period' => ['required', 'date_format:Y-m', 'before:'.now()->format('Y-m')]])['period'];

        $batch = ConsolidatedEinvoice::query()->firstOrCreate(
            ['branch_id' => $branchId, 'period' => $period],
            ['sale_count' => 0, 'subtotal_sen' => 0, 'tax_sen' => 0, 'total_sen' => 0],
        )->recalculate();

        if ($batch->sale_count === 0) {
            return $this->toast('error', "No walk-in sales left to consolidate for {$period}.");
        }

        return $this->send($batch);
    }

    public function poll(Request $request, EInvoiceDocument $einvoice): RedirectResponse
    {
        try {
            $this->einvoice->poll($einvoice);
        } catch (Throwable $e) {
            report($e);

            return $this->toast('error', 'Could not reach LHDN. Try again in a few minutes.');
        }

        return back();
    }

    public function cancel(Request $request, EInvoiceDocument $einvoice): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:300']])['reason'];

        try {
            $this->einvoice->cancel($einvoice, $reason);
        } catch (EInvoiceException $e) {
            return $this->toast('error', $e->getMessage());
        }

        AuditLog::record('einvoice.cancelled', $einvoice, ['reason' => $reason]);

        return $this->toast('success', 'Cancellation sent to LHDN.');
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tin = $user->branch?->tin;
        if (! $tin) {
            return $this->toast('error', 'Enter the company TIN under Branches first.');
        }

        $data = $request->validate([
            'environment' => ['required', Rule::enum(Environment::class)],
            'client_id' => ['nullable', 'string', 'max:100'],
            'client_secret' => ['nullable', 'string', 'max:100'],
            'unsigned' => ['boolean'],
            'certificate' => ['nullable', 'string', 'max:20000'],
            'private_key' => ['nullable', 'string', 'max:20000'],
        ]);

        $setting = EInvoiceSetting::query()->firstOrNew(['tin' => $tin, 'environment' => $data['environment']]);
        // Secrets left blank keep their saved value.
        $setting->fill(array_filter($data, fn ($v) => $v !== null && $v !== '' && ! is_bool($v)));
        $setting->unsigned = $data['environment'] === 'sandbox' && ($data['unsigned'] ?? false);

        if (! $setting->client_id || ! $setting->client_secret) {
            throw ValidationException::withMessages(['client_id' => 'Client ID and secret are required.']);
        }

        $setting->save();
        $setting->activate();
        AuditLog::record('einvoice.settings_saved', null, ['environment' => $data['environment']]);

        return $this->toast('success', 'MyInvois settings saved.');
    }

    private function send(Model&EInvoiceable $model): RedirectResponse
    {
        try {
            $this->einvoice->submit($model);
        } catch (ValidationException $e) {
            return $this->toast('error', 'LHDN needs more details: '.implode(' ', $e->errors()['einvoice'] ?? []));
        } catch (EInvoiceException $e) {
            return $this->toast('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->toast('error', 'Could not reach LHDN. Try sending again in a few minutes.');
        }

        AuditLog::record('einvoice.submitted', $model);

        return $this->toast('success', 'Sent to LHDN. Use “Check status” if it still shows submitted.');
    }

    private function toast(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return back();
    }
}
