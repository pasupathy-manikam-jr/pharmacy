<?php

namespace App\Http\Controllers\Pharmacy;

use App\Enums\PoisonGroup;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bulk create or update products from a CSV. Rows match existing products by barcode, else by name + strength.
 * All rows are validated first; nothing is saved if any row is wrong.
 */
class ProductImportController extends Controller
{
    public const COLUMNS = ['name', 'generic_name', 'strength', 'form', 'poison_group', 'barcode', 'mal_reg_no', 'unit', 'price', 'tax_rate', 'reorder_level'];

    public function create(): Response
    {
        return Inertia::render('products/Import', ['columns' => self::COLUMNS]);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, self::COLUMNS);
            fputcsv($out, ['Panadol', 'Paracetamol', '500 mg', 'Tablet', 'none', '9556000000011', 'MAL19990001A', 'tablet', '0.30', '0', '100']);
            fclose($out);
        }, 'products-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = $handle ? array_map(fn ($h) => strtolower(trim((string) $h, "\u{FEFF} ")), fgetcsv($handle) ?: []) : [];
        if ($missing = array_diff(['name', 'price'], $header)) {
            throw ValidationException::withMessages(['file' => __('Missing columns: :columns. Download the template for the expected layout.', ['columns' => implode(', ', $missing)])]);
        }

        $rows = [];
        $errors = [];
        $line = 1;
        while ($handle && ($cells = fgetcsv($handle)) !== false) {
            $line++;
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $raw = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), null));
            $row = [
                'name' => trim((string) $raw['name']),
                'generic_name' => trim((string) ($raw['generic_name'] ?? '')) ?: null,
                'strength' => trim((string) ($raw['strength'] ?? '')) ?: null,
                'form' => trim((string) ($raw['form'] ?? '')) ?: null,
                'poison_group' => trim((string) ($raw['poison_group'] ?? '')) ?: 'none',
                'barcode' => trim((string) ($raw['barcode'] ?? '')) ?: null,
                'mal_reg_no' => trim((string) ($raw['mal_reg_no'] ?? '')) ?: null,
                'unit' => trim((string) ($raw['unit'] ?? '')) ?: 'unit',
                'price_sen' => is_numeric($raw['price']) ? (int) round((float) $raw['price'] * 100) : null,
                'tax_rate_bp' => is_numeric($raw['tax_rate'] ?? 0) ? (int) round((float) ($raw['tax_rate'] ?? 0) * 100) : null,
                'reorder_level' => is_numeric($raw['reorder_level'] ?? 0) ? (int) ($raw['reorder_level'] ?? 0) : null,
            ];

            $v = Validator::make($row, [
                'name' => ['required', 'max:255'],
                'poison_group' => [Rule::enum(PoisonGroup::class)],
                'price_sen' => ['required', 'integer', 'min:0'],
                'tax_rate_bp' => ['required', 'integer', 'min:0', 'max:10000'],
                'reorder_level' => ['required', 'integer', 'min:0'],
            ]);
            foreach ($v->errors()->all() as $message) {
                $errors[] = __('Row :line: :message', ['line' => $line, 'message' => $message]);
            }
            $rows[] = $row;
        }
        if ($handle) {
            fclose($handle);
        }

        if ($errors) {
            throw ValidationException::withMessages(['file' => array_slice($errors, 0, 20)]);
        }

        [$created, $updated] = DB::transaction(function () use ($rows) {
            $created = $updated = 0;
            foreach ($rows as $row) {
                $existing = $row['barcode']
                    ? Product::query()->where('barcode', $row['barcode'])->first()
                    : Product::query()->where('name', $row['name'])->where('strength', $row['strength'])->first();
                $existing ? $existing->update($row) : Product::query()->create($row);
                $existing ? $updated++ : $created++;
            }

            return [$created, $updated];
        });

        AuditLog::record('products.imported', null, ['created' => $created, 'updated' => $updated]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Imported: :created new, :updated updated.', ['created' => $created, 'updated' => $updated])]);

        return to_route('products.index');
    }
}
