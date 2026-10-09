<?php

namespace Tests\Feature;

use App\Mail\StockDigest;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockLedger;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OperationsHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config(['einvoice.driver' => 'fake']);
        $this->seed(DatabaseSeeder::class);
        $this->owner = User::query()->where('email', 'owner@pharmacy.test')->firstOrFail();
        $this->actingAs($this->owner);
    }

    private function product(string $name): Product
    {
        return Product::query()->where('name', $name)->firstOrFail();
    }

    public function test_shift_open_sell_close_records_variance(): void
    {
        $this->post(route('shifts.store'), ['opening_float_sen' => 5000])->assertSessionHasNoErrors();
        $this->post(route('pos.store'), [
            'lines' => [['product_id' => $this->product('Panadol')->id, 'qty' => 10]],
            'payment_method' => 'cash',
            'tendered_sen' => 300,
        ])->assertSessionHasNoErrors();

        $this->post(route('shifts.close'), ['counted_cash_sen' => 5200])->assertSessionHasNoErrors();

        $shift = Shift::query()->where('user_id', $this->owner->id)->sole();
        $this->assertSame(5300, $shift->expected_cash_sen);
        $this->assertSame(-100, AuditLog::query()->where('action', 'shift.closed')->sole()->data['variance_sen'] ?? null);
    }

    public function test_partial_refund_over_http(): void
    {
        Shift::query()->create(['branch_id' => $this->owner->branch_id, 'user_id' => $this->owner->id, 'opening_float_sen' => 0, 'opened_at' => now()]);
        $this->post(route('pos.store'), ['lines' => [['product_id' => $this->product('Panadol')->id, 'qty' => 4]], 'payment_method' => 'card']);
        $sale = Sale::query()->where('user_id', $this->owner->id)->sole();
        $line = $sale->lines()->sole();

        $this->post(route('sales.refund', $sale), ['lines' => [$line->id => 1], 'reason' => 'Opened box'])->assertRedirect(route('sales.show', $sale));
        $this->post(route('sales.refund', $sale), ['lines' => [$line->id => 5], 'reason' => 'Too many'])->assertSessionHasErrors();

        $this->assertSame('partially_refunded', $sale->fresh()?->status);
        $this->get(route('sales.show', $sale))->assertOk();
    }

    public function test_purchase_order_to_goods_received(): void
    {
        $supplier = Supplier::query()->firstOrFail();
        $vitc = $this->product('Blackmores Vitamin C');

        $this->post(route('purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'lines' => [['product_id' => $vitc->id, 'qty' => 30, 'cost_sen' => 70]],
        ]);
        $order = PurchaseOrder::query()->latest('id')->firstOrFail();
        $this->post(route('purchase-orders.status', $order), ['status' => 'ordered'])->assertSessionHasNoErrors();
        $this->get(route('purchase-orders.show', $order))->assertOk();
        $this->get(route('receipts.create', ['purchase_order' => $order->id]))->assertInertia(fn ($p) => $p->where('order.id', $order->id));

        $before = app(StockLedger::class)->onHand((int) $this->owner->branch_id, $vitc->id);
        $this->post(route('receipts.store'), [
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $order->id,
            'received_on' => today()->toDateString(),
            'payment_status' => 'pending',
            'lines' => [['product_id' => $vitc->id, 'batch_no' => 'PO-B1', 'expiry_date' => today()->addYear()->toDateString(), 'qty' => 30, 'cost_sen' => 70]],
        ])->assertSessionHasNoErrors();

        $this->assertSame('received', $order->fresh()?->status);
        $this->assertSame($before + 30, app(StockLedger::class)->onHand((int) $this->owner->branch_id, $vitc->id));
        $this->post(route('purchase-orders.status', $order), ['status' => 'cancelled'])->assertSessionHasErrors('status');
    }

    public function test_stock_adjust_and_transfer_and_switch_branch(): void
    {
        $batch = Batch::query()->where('batch_no', 'B2601')->firstOrFail();
        $this->post(route('stock.adjust', $batch), ['reason' => 'count', 'counted' => 35, 'note' => 'Shelf count'])->assertSessionHasNoErrors();
        $this->assertSame(35, app(StockLedger::class)->onHand((int) $this->owner->branch_id, $batch->product_id) - 120);

        $other = Branch::query()->create(['name' => 'Cawangan Dua']);
        $this->post(route('stock.transfer.store'), ['to_branch_id' => $other->id, 'lines' => [['batch_id' => $batch->id, 'qty' => 5]]])->assertRedirect(route('stock.movements'));
        $this->assertSame(5, app(StockLedger::class)->onHand($other->id, $batch->product_id));

        $this->post(route('branches.switch', $other))->assertRedirect(route('dashboard'));
        $this->assertSame($other->id, $this->owner->fresh()?->branch_id);
        $this->get(route('stock.index'))->assertInertia(fn ($p) => $p->has('levels.data', 1));
    }

    public function test_every_report_renders_and_exports_csv(): void
    {
        foreach (['daily', 'products', 'staff', 'payments', 'valuation', 'writeoffs'] as $report) {
            $this->get(route('reports.index', ['report' => $report]))->assertOk();
            $this->get(route('reports.index', ['report' => $report, 'export' => 'csv']))->assertOk()->assertHeader('content-type', 'text/csv; charset=utf-8');
        }

        // Seeded: 30 × RM1.10 Vitamin C with RM3.00 off → RM30.00 net revenue.
        $this->get(route('reports.index', ['report' => 'products']))->assertInertia(fn ($p) => $p->where('rows.0.product', 'Blackmores Vitamin C')->where('rows.0.revenue_sen', 3000));
        // Dashboard nets today's refunds: 5 receipts, one RM9.90 syrup refunded.
        $this->get(route('dashboard'))->assertInertia(fn ($p) => $p->where('stats.sales_count', 5)->where('stats.sales_total_sen', 8510 - 990));

        $csv = $this->get(route('reports.index', ['report' => 'valuation', 'export' => 'csv']))->streamedContent();
        $this->assertStringContainsString('cost_value_sen', $csv);
    }

    public function test_product_csv_import_validates_all_rows_first(): void
    {
        $bad = UploadedFile::fake()->createWithContent('p.csv', "name,price,poison_group\nGood,1.00,none\nBad,abc,none\n");
        $this->post(route('products.import.store'), ['file' => $bad])->assertSessionHasErrors('file');
        $this->assertNull(Product::query()->where('name', 'Good')->first());

        $good = UploadedFile::fake()->createWithContent('p.csv', "name,strength,barcode,price,poison_group,reorder_level\nNew Drug,10 mg,,2.50,C,5\nPanadol,500 mg,9556000000011,0.35,none,100\n");
        $this->post(route('products.import.store'), ['file' => $good])->assertRedirect(route('products.index'));

        $this->assertSame(250, Product::query()->where('name', 'New Drug')->sole()->price_sen);
        $this->assertSame(35, $this->product('Panadol')->price_sen);
    }

    public function test_customer_payment_cannot_exceed_balance(): void
    {
        $customer = Customer::query()->firstOrFail();
        $this->post(route('customers.payment', $customer), ['amount_sen' => 100, 'method' => 'card'])->assertSessionHasErrors('amount_sen');
        $this->get(route('customers.show', $customer))->assertOk();
    }

    public function test_cashier_cannot_adjust_or_see_reports(): void
    {
        $this->actingAs(User::query()->where('email', 'cashier@pharmacy.test')->firstOrFail());
        $batch = Batch::query()->firstOrFail();

        $this->post(route('stock.adjust', $batch), ['reason' => 'damaged', 'qty' => 1])->assertForbidden();
        $this->get(route('reports.index'))->assertForbidden();
        $this->get(route('einvoice.index'))->assertForbidden();
        $this->get(route('shifts.index'))->assertOk();
    }

    public function test_stock_digest_emails_owner_and_pharmacist(): void
    {
        Mail::fake();
        Product::query()->where('name', 'Panadol')->update(['reorder_level' => 1000]);

        $this->artisan('pharmacy:stock-digest')->assertSuccessful();

        Mail::assertSent(StockDigest::class, 2);
        Mail::assertSent(StockDigest::class, fn (StockDigest $m) => $m->hasTo('owner@pharmacy.test') && count($m->low) > 0);
    }

    public function test_per_page_accepts_only_offered_sizes(): void
    {
        $this->get(route('products.index', ['per_page' => 10]))->assertInertia(fn ($p) => $p->where('products.per_page', 10)->has('products.data', 10));
        $this->get(route('products.index', ['per_page' => 5000]))->assertInertia(fn ($p) => $p->where('products.per_page', 20));
        $this->get(route('products.index', ['per_page' => 10, 'page' => 2]))->assertInertia(fn ($p) => $p->where('products.prev_page_url', fn ($url) => str_contains((string) $url, 'per_page=10')));
    }

    public function test_lists_sort_by_whitelisted_columns_only(): void
    {
        $this->get(route('products.index', ['sort' => 'price_sen', 'dir' => 'desc']))
            ->assertInertia(fn ($p) => $p->where('sort', ['sort' => 'price_sen', 'dir' => 'desc'])->where('products.data.0.name', 'Accu-Chek Test Strips'));

        $this->get(route('products.index', ['sort' => 'name;DROP TABLE products', 'dir' => 'sideways']))
            ->assertInertia(fn ($p) => $p->where('sort', ['sort' => 'name', 'dir' => 'asc']));

        $this->get(route('stock.index', ['sort' => 'value_sen', 'dir' => 'desc', 'per_page' => 10]))
            ->assertInertia(fn ($p) => $p->where('sort.sort', 'value_sen')->has('levels.data', 10));

        foreach (['sales.index' => 'total_sen', 'receipts.index' => 'supplier', 'purchase-orders.index' => 'total_sen', 'prescriptions.index' => 'patient', 'shifts.index' => 'variance', 'audit.index' => 'user', 'stock.movements' => 'qty_delta', 'customers.index' => 'dob', 'suppliers.index' => 'tin'] as $route => $column) {
            $this->get(route($route, ['sort' => $column, 'dir' => 'desc']))->assertOk()->assertInertia(fn ($p) => $p->where('sort.sort', $column));
        }
    }

    public function test_product_image_upload_replace_and_remove(): void
    {
        Storage::fake('public');
        $product = $this->product('Panadol');
        $fields = ['name' => 'Panadol', 'poison_group' => 'none', 'unit' => 'tablet', 'price_sen' => 30, 'tax_rate_bp' => 0, 'reorder_level' => 100, 'is_active' => true];

        $this->put(route('products.update', $product), [...$fields, 'image' => UploadedFile::fake()->image('a.png', 400, 300)])->assertSessionHasNoErrors();
        $first = (string) $product->fresh()?->image_path;
        Storage::disk('public')->assertExists($first);
        $this->assertStringContainsString('/storage/products/', (string) $product->fresh()?->image_url);

        $this->put(route('products.update', $product), [...$fields, 'image' => UploadedFile::fake()->image('b.jpg')])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($first);

        $this->put(route('products.update', $product), [...$fields, 'image' => UploadedFile::fake()->create('evil.svg', 5, 'image/svg+xml')])->assertSessionHasErrors('image');

        $this->put(route('products.update', $product), [...$fields, 'remove_image' => true])->assertSessionHasNoErrors();
        $this->assertNull($product->fresh()?->image_path);
    }

    public function test_goods_receipt_listing_still_works(): void
    {
        $this->get(route('receipts.index'))->assertOk();
        $this->assertSame(1, GoodsReceipt::query()->count());
    }
}
