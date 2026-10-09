<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\StockLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $branchId = $request->user()?->branch_id;

        $todaySales = Sale::query()->where('branch_id', $branchId)->whereDate('created_at', today());
        $todayRefunds = (int) Refund::query()->where('branch_id', $branchId)->whereDate('created_at', today())->sum('amount_sen');

        $nearExpiry = StockLevel::query()
            ->with('batch.product:id,name,strength')
            ->join('batches', 'batches.id', '=', 'stock_levels.batch_id')
            ->where('stock_levels.branch_id', $branchId)
            ->where('stock_levels.qty', '>', 0)
            ->whereDate('batches.expiry_date', '<=', today()->addDays(90))
            ->orderBy('batches.expiry_date')
            ->select('stock_levels.*')
            ->limit(20)
            ->get()
            ->map(fn (StockLevel $l) => [
                'product' => trim($l->batch->product->name.' '.$l->batch->product->strength),
                'batch_no' => $l->batch->batch_no,
                'expiry_date' => $l->batch->expiry_date->toDateString(),
                'expired' => $l->batch->expiry_date->lte(today()),
                'qty' => $l->qty,
            ]);

        $lowStock = DB::table('products')
            ->leftJoin('batches', 'batches.product_id', '=', 'products.id')
            ->leftJoin('stock_levels', fn ($j) => $j->on('stock_levels.batch_id', '=', 'batches.id')->where('stock_levels.branch_id', $branchId))
            ->where('products.is_active', true)
            ->groupBy('products.id', 'products.name', 'products.reorder_level')
            ->havingRaw('COALESCE(SUM(stock_levels.qty), 0) <= products.reorder_level')
            ->orderBy('products.name')
            ->limit(20)
            ->get(['products.id', 'products.name', 'products.reorder_level', DB::raw('COALESCE(SUM(stock_levels.qty), 0) as on_hand')]);

        return Inertia::render('Dashboard', [
            'stats' => [
                'sales_count' => (clone $todaySales)->count(),
                'sales_total_sen' => (int) (clone $todaySales)->sum('total_sen') - $todayRefunds,
                'near_expiry' => $nearExpiry->count(),
                'low_stock' => $lowStock->count(),
            ],
            'nearExpiry' => $nearExpiry,
            'lowStock' => $lowStock,
        ]);
    }
}
