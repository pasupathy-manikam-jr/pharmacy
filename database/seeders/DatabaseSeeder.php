<?php

namespace Database\Seeders;

use App\Actions\Pharmacy\CompleteSale;
use App\Actions\Pharmacy\ReceiveGoods;
use App\Actions\Pharmacy\RefundSale;
use App\Enums\PoisonGroup;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shift;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeded staff accounts. With DEMO_LOGINS=true the login page offers them as
     * one-click logins, so never enable that flag on a live server.
     */
    public const LOGINS = [
        ['name' => 'Owner', 'email' => 'owner@pharmacy.test', 'password' => 'Zx123456', 'role' => 'owner'],
        ['name' => 'Lim Wei Ling', 'email' => 'pharmacist@pharmacy.test', 'password' => 'Zx123456', 'role' => 'pharmacist'],
        ['name' => 'Siti Rahman', 'email' => 'assistant@pharmacy.test', 'password' => 'Zx123456', 'role' => 'assistant'],
        ['name' => 'Ravi Kumar', 'email' => 'cashier@pharmacy.test', 'password' => 'Zx123456', 'role' => 'cashier'],
    ];

    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $branch = Branch::query()->create([
            'name' => 'Farmasi Seri Mutiara',
            'licence_no' => 'A/12345',
            'address' => '12 Jalan Bunga Raya, 50250 Kuala Lumpur',
            'phone' => '03-2141 0000',
            'company_name' => 'Seri Mutiara Pharmacy Sdn Bhd',
            'postcode' => '50250',
            'city' => 'Kuala Lumpur',
            'state' => '14',
            'email' => 'hello@serimutiara.test',
        ]);

        foreach (self::LOGINS as $login) {
            User::factory()->create([
                'name' => $login['name'],
                'email' => $login['email'],
                'password' => $login['password'],
                'branch_id' => $branch->id,
            ])->assignRole($login['role']);
        }

        $supplier = Supplier::query()->create(['name' => 'Zuellig Pharma Sdn Bhd', 'tin' => 'C1234567890', 'phone' => '03-7841 5000']);
        Supplier::query()->create(['name' => 'DKSH Malaysia Sdn Bhd', 'phone' => '03-7882 8888']);

        $catalogue = [
            // name, generic, strength, form, group, barcode, price RM, reorder
            ['Panadol', 'Paracetamol', '500 mg', 'Tablet', PoisonGroup::None, '9556000000011', 0.30, 100],
            ['Strepsils', 'Amylmetacresol', '', 'Lozenge', PoisonGroup::None, '9556000000028', 0.80, 50],
            ['Clarinase', 'Loratadine / Pseudoephedrine', '5/120 mg', 'Tablet', PoisonGroup::C, '9556000000035', 2.10, 30],
            ['Amoxil', 'Amoxicillin', '500 mg', 'Capsule', PoisonGroup::B, '9556000000042', 1.20, 60],
            ['Ventolin Inhaler', 'Salbutamol', '100 mcg', 'Inhaler', PoisonGroup::B, '9556000000059', 18.50, 5],
            ['Piriton', 'Chlorphenamine', '4 mg', 'Tablet', PoisonGroup::C, '9556000000066', 0.25, 50],
            ['Uphamol Cough Syrup', 'Dextromethorphan', '15 mg/5 ml', 'Syrup', PoisonGroup::D, '9556000000073', 9.90, 10],
            ['Blackmores Vitamin C', 'Ascorbic acid', '1000 mg', 'Tablet', PoisonGroup::None, '9556000000080', 1.10, 40],
        ];

        $products = collect($catalogue)->map(fn ($p) => Product::query()->create([
            'name' => $p[0], 'generic_name' => $p[1], 'strength' => $p[2] ?: null, 'form' => $p[3],
            'poison_group' => $p[4], 'barcode' => $p[5], 'price_sen' => (int) round($p[6] * 100),
            'reorder_level' => $p[7], 'unit' => strtolower($p[3]),
        ]));

        $lines = [];
        foreach ($products as $i => $product) {
            $lines[] = ['product_id' => $product->id, 'batch_no' => 'B'.(2601 + $i), 'expiry_date' => today()->addMonths(2 + $i)->toDateString(), 'qty' => 40 + 10 * $i, 'cost_sen' => (int) ($product->price_sen * 0.6)];
            $lines[] = ['product_id' => $product->id, 'batch_no' => 'B'.(2701 + $i), 'expiry_date' => today()->addMonths(14 + $i)->toDateString(), 'qty' => 120, 'cost_sen' => (int) ($product->price_sen * 0.6)];
        }

        app(ReceiveGoods::class)->handle(User::query()->where('email', 'owner@pharmacy.test')->firstOrFail(), [
            'supplier_id' => $supplier->id,
            'invoice_no' => 'ZP-OPENING',
            'received_on' => today()->toDateString(),
            'payment_status' => 'paid',
            'lines' => $lines,
        ]);

        $aisyah = Customer::query()->create(['name' => 'Nur Aisyah binti Ahmad', 'ic_no' => '900101-14-5678', 'sex' => 'F', 'dob' => '1990-01-01', 'phone' => '012-345 6789', 'citizenship' => 'Malaysian', 'allergies' => 'Penicillin, Amoxicillin']);
        $tan = Customer::query()->create(['name' => 'Tan Ah Kow', 'ic_no' => '650505-10-1234', 'sex' => 'M', 'dob' => '1965-05-05', 'citizenship' => 'Malaysian']);
        Customer::query()->create(['name' => 'Klinik Harmoni Sdn Bhd', 'tin' => 'C20880099010', 'brn' => '201501012345', 'phone' => '03-2142 1111', 'address' => '8 Jalan Ampang, Kuala Lumpur', 'email' => 'accounts@harmoni.test']);

        $this->demoTrading($branch, $products->keyBy('name')->all(), $tan, $supplier);
    }

    /**
     * A closed shift with a few sales, a credit sale, a partial refund and a draft purchase order,
     * so the dashboard, reports and registers have something to show.
     *
     * @param  array<string, Product>  $products
     */
    private function demoTrading(Branch $branch, array $products, Customer $tan, Supplier $supplier): void
    {
        $pharmacist = User::query()->where('email', 'pharmacist@pharmacy.test')->firstOrFail();
        $shift = Shift::query()->create(['branch_id' => $branch->id, 'user_id' => $pharmacist->id, 'opening_float_sen' => 20000, 'opened_at' => now()->subHours(3)]);
        $sell = app(CompleteSale::class);

        $sell->handle($pharmacist, ['lines' => [['product_id' => $products['Panadol']->id, 'qty' => 20], ['product_id' => $products['Strepsils']->id, 'qty' => 2]], 'payment_method' => 'cash', 'tendered_sen' => 1000]);
        $sell->handle($pharmacist, ['lines' => [['product_id' => $products['Blackmores Vitamin C']->id, 'qty' => 30]], 'payment_method' => 'card', 'discount_sen' => 300]);
        $sell->handle($pharmacist, ['customer_id' => $tan->id, 'lines' => [['product_id' => $products['Piriton']->id, 'qty' => 10, 'dosage' => '1 tablet at night when needed']], 'payment_method' => 'ewallet']);
        $sell->handle($pharmacist, [
            'customer_id' => $tan->id,
            'prescription' => ['prescriber_name' => 'Dr. Tan Mei Ling', 'prescriber_reg_no' => 'MMC 45821', 'clinic' => 'Klinik Harmoni', 'diagnosis' => 'Upper respiratory tract infection', 'issued_on' => today()->toDateString(), 'refills_allowed' => 1],
            'lines' => [['product_id' => $products['Amoxil']->id, 'qty' => 21, 'dosage' => '1 capsule 3 times a day after food']],
            'payment_method' => 'credit',
        ]);
        $cough = $sell->handle($pharmacist, ['customer_id' => $tan->id, 'lines' => [['product_id' => $products['Uphamol Cough Syrup']->id, 'qty' => 2, 'dosage' => '10 ml 3 times a day']], 'payment_method' => 'cash', 'tendered_sen' => 2000]);

        app(RefundSale::class)->handle($pharmacist, $cough, [$cough->lines()->firstOrFail()->id => 1], 'Customer bought one too many');

        $shift->update(['closed_at' => now()->subHour(), 'expected_cash_sen' => $shift->cashSummary()['expected'], 'counted_cash_sen' => $shift->cashSummary()['expected']]);

        $po = PurchaseOrder::query()->create(['branch_id' => $branch->id, 'supplier_id' => $supplier->id, 'number' => 'PO'.now()->format('ym').'-00001', 'user_id' => $pharmacist->id, 'expected_on' => today()->addDays(3)->toDateString()]);
        $po->lines()->createMany([
            ['product_id' => $products['Ventolin Inhaler']->id, 'qty' => 10, 'cost_sen' => 1100],
            ['product_id' => $products['Clarinase']->id, 'qty' => 60, 'cost_sen' => 120],
        ]);

    }
}
