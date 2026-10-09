<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only reports over the ledger. Each report is one query; `?export=csv` streams the same rows.
 */
class ReportController extends Controller
{
    public const REPORTS = [
        'daily' => 'Daily takings',
        'products' => 'Sales by product',
        'staff' => 'Sales by staff',
        'payments' => 'Payment methods',
        'valuation' => 'Stock valuation',
        'writeoffs' => 'Write-offs',
    ];

    public function __invoke(Request $request): Response|StreamedResponse
    {
        $filters = $request->validate([
            'report' => ['nullable', 'in:'.implode(',', array_keys(self::REPORTS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $report = $filters['report'] ?? 'daily';
        $from = $filters['from'] ?? today()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? today()->toDateString();
        $branchId = (int) $request->user()?->branch_id;

        $rows = $this->rows($report, $branchId, $from.' 00:00:00', $to.' 23:59:59');

        if ($request->query('export') === 'csv') {
            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                if ($out === false) {
                    return;
                }
                if ($first = $rows->first()) {
                    fputcsv($out, array_keys((array) $first));
                }
                foreach ($rows as $row) {
                    fputcsv($out, array_map(fn ($v) => is_scalar($v) || $v === null ? $v : json_encode($v), (array) $row));
                }
                fclose($out);
            }, "{$report}-{$from}-{$to}.csv", ['Content-Type' => 'text/csv']);
        }

        return Inertia::render('reports/Index', [
            'rows' => $rows,
            'filters' => ['report' => $report, 'from' => $from, 'to' => $to],
            'reports' => self::REPORTS,
        ]);
    }

    /**
     * Money columns end in _sen.
     *
     * @return Collection<int, object>
     */
    private function rows(string $report, int $branchId, string $from, string $to): Collection
    {
        $sales = fn () => DB::table('sales')->where('sales.branch_id', $branchId)->whereBetween('sales.created_at', [$from, $to]);
        $refunds = DB::table('refunds')->where('refunds.branch_id', $branchId)->whereBetween('refunds.created_at', [$from, $to]);

        return match ($report) {
            'daily' => $sales()
                ->selectRaw('DATE(sales.created_at) as day, COUNT(*) as receipts, SUM(subtotal_sen) as subtotal_sen, SUM(discount_sen) as discount_sen, SUM(tax_sen) as tax_sen, SUM(total_sen) as total_sen')
                ->groupByRaw('DATE(sales.created_at)')
                ->orderBy('day')
                ->get()
                ->map(function ($r) use ($refunds) {
                    $r->refunds_sen = (int) (clone $refunds)->whereRaw('DATE(refunds.created_at) = ?', [$r->day])->sum('amount_sen');
                    $r->net_sen = (int) $r->total_sen - $r->refunds_sen;

                    return $r;
                }),
            'products' => $sales()
                ->join('sale_lines', 'sale_lines.sale_id', '=', 'sales.id')
                ->join('products', 'products.id', '=', 'sale_lines.product_id')
                ->join('batches', 'batches.id', '=', 'sale_lines.batch_id')
                ->selectRaw('products.name as product, products.strength, SUM(sale_lines.qty - sale_lines.refunded_qty) as qty, SUM((sale_lines.qty - sale_lines.refunded_qty) * sale_lines.price_sen * (1 - sales.discount_sen * 1.0 / NULLIF(sales.subtotal_sen, 0))) as revenue_sen, SUM((sale_lines.qty - sale_lines.refunded_qty) * batches.cost_sen) as cost_sen')
                ->groupBy('products.id', 'products.name', 'products.strength')
                ->orderByDesc('revenue_sen')
                ->get()
                ->map(function ($r) {
                    $r->revenue_sen = (int) round((float) $r->revenue_sen);
                    $r->margin_sen = (int) $r->revenue_sen - (int) $r->cost_sen;
                    $r->margin_pct = (int) $r->revenue_sen ? round($r->margin_sen * 100 / (int) $r->revenue_sen, 1) : 0;

                    return $r;
                }),
            'staff' => $sales()
                ->join('users', 'users.id', '=', 'sales.user_id')
                ->selectRaw('users.name as staff, COUNT(*) as receipts, SUM(total_sen) as total_sen, SUM(discount_sen) as discount_sen')
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('total_sen')
                ->get(),
            'payments' => $sales()
                ->selectRaw('payment_method as method, COUNT(*) as receipts, SUM(total_sen) as total_sen')
                ->groupBy('payment_method')
                ->orderByDesc('total_sen')
                ->get(),
            'valuation' => DB::table('stock_levels')
                ->join('batches', 'batches.id', '=', 'stock_levels.batch_id')
                ->join('products', 'products.id', '=', 'batches.product_id')
                ->where('stock_levels.branch_id', $branchId)
                ->where('stock_levels.qty', '>', 0)
                ->selectRaw('products.name as product, products.strength, SUM(stock_levels.qty) as qty, SUM(stock_levels.qty * batches.cost_sen) as cost_value_sen, SUM(stock_levels.qty * products.price_sen) as retail_value_sen')
                ->groupBy('products.id', 'products.name', 'products.strength')
                ->orderByDesc('cost_value_sen')
                ->get(),
            'writeoffs' => DB::table('stock_movements')
                ->join('batches', 'batches.id', '=', 'stock_movements.batch_id')
                ->join('products', 'products.id', '=', 'batches.product_id')
                ->leftJoin('users', 'users.id', '=', 'stock_movements.user_id')
                ->where('stock_movements.branch_id', $branchId)
                ->where('stock_movements.type', 'adjustment')
                ->where('stock_movements.qty_delta', '<', 0)
                ->whereBetween('stock_movements.created_at', [$from, $to])
                ->selectRaw('DATE(stock_movements.created_at) as day, products.name as product, batches.batch_no, stock_movements.reason, -stock_movements.qty_delta as qty, -stock_movements.qty_delta * batches.cost_sen as cost_sen, users.name as staff, stock_movements.note')
                ->orderBy('stock_movements.id')
                ->get(),
            default => collect(),
        };
    }
}
