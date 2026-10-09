<?php

namespace Tests\Feature;

use App\Actions\Pharmacy\AdjustStock;
use App\Actions\Pharmacy\CompleteSale;
use App\Actions\Pharmacy\ReceiveGoods;
use App\Actions\Pharmacy\RefundSale;
use App\Actions\Pharmacy\TransferStock;
use App\Enums\PoisonGroup;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ConsolidatedEinvoice;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockLedger;
use App\Support\EInvoiceBuilder;
use Database\Seeders\RoleSeeder;
use EInvoiceSdk\EInvoice;
use EInvoiceSdk\Exceptions\EInvoiceException;
use EInvoiceSdk\Models\EInvoiceSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $pharmacist;

    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();
        config(['einvoice.driver' => 'fake']);
        $this->seed(RoleSeeder::class);
        $this->branch = Branch::query()->create(['name' => 'Main', 'tin' => 'C12345678901', 'brn' => '202101001341', 'address' => '1 Jalan', 'city' => 'Kuala Lumpur', 'state' => '14', 'phone' => '0321410000']);
        $this->pharmacist = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('pharmacist');
        $this->shift = Shift::query()->create(['branch_id' => $this->branch->id, 'user_id' => $this->pharmacist->id, 'opening_float_sen' => 10000, 'opened_at' => now()]);
        $this->actingAs($this->pharmacist);
    }

    private function stocked(int $priceSen = 1000, PoisonGroup $group = PoisonGroup::None, int $qty = 50, int $taxBp = 0): Product
    {
        $p = Product::query()->create(['name' => 'P'.uniqid(), 'poison_group' => $group, 'price_sen' => $priceSen, 'tax_rate_bp' => $taxBp]);
        app(ReceiveGoods::class)->handle($this->pharmacist, [
            'supplier_id' => Supplier::query()->firstOrCreate(['name' => 'S'])->id,
            'received_on' => today()->toDateString(),
            'payment_status' => 'paid',
            'lines' => [['product_id' => $p->id, 'batch_no' => 'B'.$p->id, 'expiry_date' => today()->addYear()->toDateString(), 'qty' => $qty, 'cost_sen' => 100]],
        ]);

        return $p;
    }

    /** @param  array<string, mixed>  $extra */
    private function sell(Product $p, int $qty, array $extra = []): Sale
    {
        return app(CompleteSale::class)->handle($this->pharmacist, [
            'lines' => [['product_id' => $p->id, 'qty' => $qty]],
            'payment_method' => 'card',
            ...$extra,
        ]);
    }

    public function test_sale_requires_open_shift(): void
    {
        $p = $this->stocked();
        $this->shift->update(['closed_at' => now()]);

        $this->expectException(ValidationException::class);
        $this->sell($p, 1);
    }

    public function test_partial_refunds_pay_back_exact_net_with_discount(): void
    {
        $p = $this->stocked(333);
        $sale = $this->sell($p, 3, ['discount_sen' => 100]); // 999 - 100 = 899
        $line = $sale->lines()->sole();

        $r1 = app(RefundSale::class)->handle($this->pharmacist, $sale, [$line->id => 1], 'Wrong item');
        $this->assertSame('partially_refunded', $sale->fresh()?->status);
        $r2 = app(RefundSale::class)->handle($this->pharmacist, $sale, [$line->id => 2], 'Wrong item');

        $this->assertSame(899, $r1->amount_sen + $r2->amount_sen);
        $this->assertSame('refunded', $sale->fresh()?->status);
        $this->assertSame(50, app(StockLedger::class)->onHand($this->branch->id, $p->id));
    }

    public function test_credit_sale_needs_customer_and_builds_balance(): void
    {
        $p = $this->stocked(1000);
        $customer = Customer::query()->create(['name' => 'Ali']);

        try {
            $this->sell($p, 1, ['payment_method' => 'credit']);
            $this->fail('Credit sale without customer');
        } catch (ValidationException) {
        }

        $sale = $this->sell($p, 3, ['payment_method' => 'credit', 'customer_id' => $customer->id]);
        app(RefundSale::class)->handle($this->pharmacist, $sale, [$sale->lines()->sole()->id => 1], 'Returned');
        CustomerPayment::query()->create(['customer_id' => $customer->id, 'branch_id' => $this->branch->id, 'user_id' => $this->pharmacist->id, 'amount_sen' => 500, 'method' => 'cash', 'shift_id' => $this->shift->id]);

        $this->assertSame(3000 - 1000 - 500, $customer->balanceSen());
    }

    public function test_refills_are_limited(): void
    {
        $p = $this->stocked(500, PoisonGroup::B);
        $customer = Customer::query()->create(['name' => 'Ali']);
        $first = $this->sell($p, 1, ['customer_id' => $customer->id, 'prescription' => ['prescriber_name' => 'Dr Tan', 'issued_on' => today()->toDateString(), 'refills_allowed' => 1]]);

        $this->sell($p, 1, ['customer_id' => $customer->id, 'prescription_id' => $first->prescription_id]);

        $this->expectException(ValidationException::class);
        $this->sell($p, 1, ['customer_id' => $customer->id, 'prescription_id' => $first->prescription_id]);
    }

    public function test_shift_expected_cash(): void
    {
        $p = $this->stocked(1000);
        $sale = $this->sell($p, 2, ['payment_method' => 'cash', 'tendered_sen' => 5000]);
        $this->sell($p, 1); // card, not in drawer
        app(RefundSale::class)->handle($this->pharmacist, $sale, [$sale->lines()->sole()->id => 1], 'Returned');

        $this->assertSame(10000 + 2000 - 1000, $this->shift->cashSummary()['expected']);
    }

    public function test_adjustment_cannot_go_negative_and_records_reason(): void
    {
        $p = $this->stocked(qty: 5);
        $batch = Batch::query()->where('product_id', $p->id)->sole();

        $movement = app(AdjustStock::class)->handle($this->pharmacist, $batch, -2, 'damaged', 'Dropped');
        $this->assertSame('damaged', $movement?->reason);
        $this->assertSame(3, $movement?->qty_after);

        $this->expectException(ValidationException::class);
        app(AdjustStock::class)->handle($this->pharmacist, $batch, -4, 'damaged');
    }

    public function test_transfer_moves_batch_between_branches(): void
    {
        $p = $this->stocked(qty: 10);
        $other = Branch::query()->create(['name' => 'Second']);
        $batch = Batch::query()->where('product_id', $p->id)->sole();

        app(TransferStock::class)->handle($this->pharmacist, $other, [['batch_id' => $batch->id, 'qty' => 4]]);

        $this->assertSame(6, app(StockLedger::class)->onHand($this->branch->id, $p->id));
        $this->assertSame(4, app(StockLedger::class)->onHand($other->id, $p->id));
    }

    public function test_allocate_shares_sum_exactly(): void
    {
        $this->assertSame([33, 33, 34], EInvoiceBuilder::allocate(100, [1, 1, 1]));
        $this->assertSame([0, 0], EInvoiceBuilder::allocate(0, [5, 5]));
    }

    public function test_individual_einvoice_needs_customer_tin_then_submits(): void
    {
        EInvoiceSetting::query()->create(['tin' => 'C12345678901', 'environment' => 'sandbox', 'client_id' => 'x', 'client_secret' => 'y', 'unsigned' => true])->activate();
        $p = $this->stocked(1000, taxBp: 800);
        $customer = Customer::query()->create(['name' => 'Syarikat ABC', 'tin' => 'C98765432100', 'brn' => '201901000005', 'address' => '2 Jalan', 'phone' => '0123456789']);

        $walkIn = $this->sell($p, 1);
        try {
            $walkIn->toEInvoiceDocument();
            $this->fail('Walk-in sale built an individual e-invoice');
        } catch (EInvoiceException) {
        }

        $sale = $this->sell($p, 2, ['customer_id' => $customer->id, 'discount_sen' => 200]);
        $doc = $sale->toEInvoiceDocument();
        $this->assertSame(19.6, $doc->grandTotal); // 20.00 + 1.60 tax − 2.00 discount
        $this->assertSame([], app(EInvoice::class)->validate($doc));

        $record = app(EInvoice::class)->submit($sale);
        $this->assertSame($sale->number, $record->number);
    }

    public function test_consolidated_excludes_individually_invoiced_and_nets_refunds(): void
    {
        EInvoiceSetting::query()->create(['tin' => 'C12345678901', 'environment' => 'sandbox', 'client_id' => 'x', 'client_secret' => 'y', 'unsigned' => true])->activate();
        $p = $this->stocked(1000);
        $a = $this->sell($p, 2);
        $this->sell($p, 1);
        app(RefundSale::class)->handle($this->pharmacist, $a, [$a->lines()->sole()->id => 1], 'Returned');

        $batch = ConsolidatedEinvoice::query()->create(['branch_id' => $this->branch->id, 'period' => now()->format('Y-m'), 'sale_count' => 0, 'subtotal_sen' => 0, 'tax_sen' => 0, 'total_sen' => 0])->recalculate();

        $this->assertSame(2, $batch->sale_count);
        $this->assertSame(2000, $batch->total_sen);
        $doc = $batch->toEInvoiceDocument();
        $this->assertTrue($doc->consolidated);
        $this->assertSame([], app(EInvoice::class)->validate($doc));
    }
}
