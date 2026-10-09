<?php

namespace App\Console\Commands;

use App\Mail\StockDigest;
use App\Models\Branch;
use App\Models\StockLevel;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

#[Signature('pharmacy:stock-digest')]
#[Description('Email owners and pharmacists each branch’s expiring and low stock')]
class SendStockDigest extends Command
{
    public function handle(): int
    {
        foreach (Branch::all() as $branch) {
            $expiring = StockLevel::query()
                ->select('stock_levels.*')
                ->join('batches', 'batches.id', '=', 'stock_levels.batch_id')
                ->where('stock_levels.branch_id', $branch->id)
                ->where('stock_levels.qty', '>', 0)
                ->whereDate('batches.expiry_date', '<=', today()->addDays(60))
                ->orderBy('batches.expiry_date')
                ->with('batch.product:id,name,strength')
                ->get()
                ->map(fn (StockLevel $l) => [
                    'product' => trim($l->batch->product->name.' '.$l->batch->product->strength),
                    'batch_no' => $l->batch->batch_no,
                    'expiry_date' => $l->batch->expiry_date->toDateString(),
                    'qty' => $l->qty,
                ])
                ->values()
                ->all();
            $expiring = array_values($expiring);

            $low = DB::table('products')
                ->leftJoin('batches', 'batches.product_id', '=', 'products.id')
                ->leftJoin('stock_levels', fn ($j) => $j->on('stock_levels.batch_id', '=', 'batches.id')->where('stock_levels.branch_id', $branch->id))
                ->where('products.is_active', true)
                ->where('products.reorder_level', '>', 0)
                ->groupBy('products.id', 'products.name', 'products.reorder_level')
                ->havingRaw('COALESCE(SUM(stock_levels.qty), 0) <= products.reorder_level')
                ->orderBy('products.name')
                ->get(['products.name', 'products.reorder_level', DB::raw('COALESCE(SUM(stock_levels.qty), 0) as on_hand')])
                ->map(fn ($r) => ['name' => (string) $r->name, 'reorder_level' => (int) $r->reorder_level, 'on_hand' => (int) $r->on_hand])
                ->all();
            $low = array_values($low);

            if (! $expiring && ! $low) {
                continue;
            }

            $recipients = User::query()->where('branch_id', $branch->id)->role(['owner', 'pharmacist'])->get();
            foreach ($recipients as $user) {
                Mail::to($user)->send(new StockDigest($branch, $expiring, $low));
            }

            $this->info("{$branch->name}: ".count($expiring).' expiring, '.count($low).' low, sent to '.$recipients->count());
        }

        return self::SUCCESS;
    }
}
