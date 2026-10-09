<?php

namespace Tests\Feature;

use App\Actions\Pharmacy\CompleteSale;
use App\Actions\Pharmacy\ReceiveGoods;
use App\Actions\Pharmacy\RefundSale;
use App\Enums\PoisonGroup;
use App\Models\Branch;
use App\Models\PoisonRegisterEntry;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockLedger;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class PharmacyFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $pharmacist;

    private User $cashier;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $branch = Branch::query()->create(['name' => 'Main']);
        $this->pharmacist = User::factory()->create(['branch_id' => $branch->id])->assignRole('pharmacist');
        $this->cashier = User::factory()->create(['branch_id' => $branch->id])->assignRole('cashier');
        $this->supplier = Supplier::query()->create(['name' => 'Supplier']);

        foreach ([$this->pharmacist, $this->cashier] as $user) {
            Shift::query()->create(['branch_id' => $branch->id, 'user_id' => $user->id, 'opening_float_sen' => 10000, 'opened_at' => now()]);
        }
    }

    private function product(PoisonGroup $group = PoisonGroup::None): Product
    {
        return Product::query()->create(['name' => 'Drug '.$group->value, 'poison_group' => $group, 'price_sen' => 500]);
    }

    /** @param  list<array{string, string, int}>  $batches  [batch_no, expiry, qty] */
    private function receive(Product $product, array $batches): void
    {
        app(ReceiveGoods::class)->handle($this->pharmacist, [
            'supplier_id' => $this->supplier->id,
            'received_on' => today()->toDateString(),
            'payment_status' => 'paid',
            'lines' => array_map(fn ($b) => ['product_id' => $product->id, 'batch_no' => $b[0], 'expiry_date' => $b[1], 'qty' => $b[2], 'cost_sen' => 200], $batches),
        ]);
    }

    /** @param  array<string, mixed>  $extra */
    private function sell(User $user, Product $product, int $qty, array $extra = []): Sale
    {
        return app(CompleteSale::class)->handle($user, [
            'lines' => [['product_id' => $product->id, 'qty' => $qty]],
            'payment_method' => 'card',
            ...$extra,
        ]);
    }

    public function test_sale_allocates_fefo_and_skips_expired_batches(): void
    {
        $p = $this->product();
        $this->receive($p, [
            ['LATE', today()->addYear()->toDateString(), 10],
            ['EXPIRED', today()->subDay()->toDateString(), 10],
            ['SOON', today()->addMonth()->toDateString(), 3],
        ]);

        $sale = $this->sell($this->cashier, $p, 5);

        $this->assertSame([['SOON', 3], ['LATE', 2]], $sale->lines()->with('batch')->get()->map(fn ($l) => [$l->batch->batch_no, $l->qty])->all());
        $this->assertSame(2500, $sale->total_sen);
        $this->assertSame(18, app(StockLedger::class)->onHand((int) $this->cashier->branch_id, $p->id));
    }

    public function test_cannot_sell_more_than_unexpired_stock(): void
    {
        $p = $this->product();
        $this->receive($p, [['EXP', today()->toDateString(), 50], ['OK', today()->addYear()->toDateString(), 2]]);

        $this->expectException(ValidationException::class);
        $this->sell($this->cashier, $p, 3);
    }

    public function test_poison_requires_pharmacist_and_writes_register(): void
    {
        $p = $this->product(PoisonGroup::C);
        $this->receive($p, [['C1', today()->addYear()->toDateString(), 10]]);

        try {
            $this->sell($this->cashier, $p, 1, ['customer' => ['name' => 'Ali']]);
            $this->fail('Cashier sold a poison');
        } catch (ValidationException) {
        }

        $this->sell($this->pharmacist, $p, 2, ['customer' => ['name' => 'Ali', 'ic_no' => '900101-01-1234']]);

        $entry = PoisonRegisterEntry::query()->sole();
        $this->assertSame('prescription_book', $entry->register);
        $this->assertSame(8, $entry->balance_after);
        $this->assertSame('Ali', $entry->customer_name);
    }

    public function test_group_b_requires_prescription(): void
    {
        $p = $this->product(PoisonGroup::B);
        $this->receive($p, [['B1', today()->addYear()->toDateString(), 10]]);

        try {
            $this->sell($this->pharmacist, $p, 1, ['customer' => ['name' => 'Ali']]);
            $this->fail('Group B sold without prescription');
        } catch (ValidationException) {
        }

        $sale = $this->sell($this->pharmacist, $p, 1, [
            'customer' => ['name' => 'Ali'],
            'prescription' => ['prescriber_name' => 'Dr Tan', 'prescriber_reg_no' => 'MMC123', 'issued_on' => today()->toDateString()],
        ]);
        $this->assertNotNull($sale->prescription_id);
        $this->assertSame('Dr Tan MMC123', PoisonRegisterEntry::query()->sole()->prescriber);
    }

    public function test_refund_restores_stock_and_reverses_register_once(): void
    {
        $p = $this->product(PoisonGroup::D);
        $this->receive($p, [['D1', today()->addYear()->toDateString(), 10]]);
        $sale = $this->sell($this->pharmacist, $p, 4, ['customer' => ['name' => 'Ali']]);

        $line = $sale->lines()->sole();

        app(RefundSale::class)->handle($this->pharmacist, $sale, [$line->id => 4], 'Changed mind');

        $this->assertSame(10, app(StockLedger::class)->onHand((int) $this->pharmacist->branch_id, $p->id));
        $this->assertSame([4, -4], PoisonRegisterEntry::query()->orderBy('id')->pluck('qty')->all());
        $this->assertSame('refunded', $sale->fresh()?->status);

        $this->expectException(ValidationException::class);
        app(RefundSale::class)->handle($this->pharmacist, $sale, [$line->id => 1], 'Again');
    }

    public function test_register_is_append_only(): void
    {
        $p = $this->product(PoisonGroup::D);
        $this->receive($p, [['D1', today()->addYear()->toDateString(), 10]]);
        $this->sell($this->pharmacist, $p, 1, ['customer' => ['name' => 'Ali']]);

        $this->expectException(LogicException::class);
        PoisonRegisterEntry::query()->sole()->update(['qty' => 99]);
    }

    public function test_cash_sale_rejects_short_tender(): void
    {
        $p = $this->product();
        $this->receive($p, [['X', today()->addYear()->toDateString(), 10]]);

        $this->expectException(ValidationException::class);
        $this->sell($this->cashier, $p, 2, ['payment_method' => 'cash', 'tendered_sen' => 999]);
    }
}
