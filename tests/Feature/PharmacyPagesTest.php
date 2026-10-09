<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PharmacyPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function user(string $role): User
    {
        return User::query()->where('email', "$role@pharmacy.test")->firstOrFail();
    }

    public function test_owner_can_open_every_page(): void
    {
        $this->actingAs($this->user('owner'));

        $pages = [
            'dashboard' => 'Dashboard',
            'pos.index' => 'pos/Index',
            'sales.index' => 'sales/Index',
            'customers.index' => 'customers/Index',
            'stock.index' => 'stock/Index',
            'products.index' => 'products/Index',
            'products.create' => 'products/Form',
            'suppliers.index' => 'suppliers/Index',
            'receipts.index' => 'receipts/Index',
            'receipts.create' => 'receipts/Create',
            'register.index' => 'register/Index',
            'users.index' => 'users/Index',
            'shifts.index' => 'shifts/Index',
            'prescriptions.index' => 'prescriptions/Index',
            'purchase-orders.index' => 'purchase-orders/Index',
            'purchase-orders.create' => 'purchase-orders/Create',
            'stock.movements' => 'stock/Movements',
            'stock.transfer' => 'stock/Transfer',
            'reports.index' => 'reports/Index',
            'audit.index' => 'audit/Index',
            'branches.index' => 'branches/Index',
            'einvoice.index' => 'einvoice/Index',
            'products.import' => 'products/Import',
        ];

        foreach ($pages as $route => $component) {
            $this->get(route($route))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component($component));
        }
    }

    public function test_cashier_cannot_reach_registers_products_or_staff(): void
    {
        $this->actingAs($this->user('cashier'));

        $this->get(route('register.index'))->assertForbidden();
        $this->get(route('products.index'))->assertForbidden();
        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('pos.index'))->assertOk();
    }

    public function test_pos_sale_redirects_to_receipt(): void
    {
        $cashier = $this->user('cashier');
        $this->actingAs($cashier);
        $panadol = Product::query()->where('name', 'Panadol')->firstOrFail();

        $this->post(route('shifts.store'), ['opening_float_sen' => 10000])->assertSessionHasNoErrors();

        $response = $this->post(route('pos.store'), [
            'lines' => [['product_id' => $panadol->id, 'qty' => 10]],
            'payment_method' => 'cash',
            'tendered_sen' => 500,
        ]);

        $sale = Sale::query()->where('user_id', $cashier->id)->sole();
        $response->assertRedirect(route('sales.show', $sale));
        $this->assertSame(300, $sale->total_sen);
        $this->get(route('sales.show', $sale))->assertOk();
    }

    public function test_login_page_lists_demo_logins_only_when_enabled(): void
    {
        config(['app.demo_logins' => true]);
        $this->get(route('login'))->assertInertia(fn (AssertableInertia $page) => $page->has('demoLogins', 4));

        config(['app.demo_logins' => false]);
        $this->get(route('login'))->assertInertia(fn (AssertableInertia $page) => $page->has('demoLogins', 0));
    }
}
