<?php

namespace Database\Seeders;

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
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonInterface;
use EInvoiceSdk\EInvoice;
use EInvoiceSdk\Models\EInvoiceSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Thirty days of backdated trading on top of DatabaseSeeder: shifts, sales, refunds, account payments,
 * purchase orders in every state, write-offs, a second branch with a transfer, and sandbox e-invoices.
 * Uses the real actions with Carbon's test clock, so every ledger, register and audit row is genuine.
 */
class DemoHistorySeeder extends Seeder
{
    private const DAYS = 30;

    public function run(): void
    {
        mt_srand(2026);
        $real = now();
        $main = Branch::query()->orderBy('id')->firstOrFail();
        $owner = User::query()->where('email', 'owner@pharmacy.test')->firstOrFail();
        $pharmacist = User::query()->where('email', 'pharmacist@pharmacy.test')->firstOrFail();
        $cashier = User::query()->where('email', 'cashier@pharmacy.test')->firstOrFail();
        $assistant = User::query()->where('email', 'assistant@pharmacy.test')->firstOrFail();

        $suppliers = collect([
            Supplier::query()->where('name', 'like', 'DKSH%')->firstOrFail(),
            Supplier::query()->create(['name' => 'Apex Pharmacy Marketing Sdn Bhd', 'tin' => 'C10203040506', 'phone' => '03-5569 1000', 'email' => 'orders@apex.test']),
            Supplier::query()->create(['name' => 'Pharmaniaga Logistics Sdn Bhd', 'tin' => 'C20304050607', 'phone' => '03-3342 9999', 'email' => 'cs@pharmaniaga.test']),
        ]);

        $customers = collect([
            ['Ahmad Faizal bin Razak', '780312-08-5521', 'M', '1978-03-12', '013-220 1188', null],
            ['Lee Siew Mei', '850921-14-6620', 'F', '1985-09-21', '012-889 0021', 'Aspirin'],
            ['Rajesh a/l Muthu', '700105-10-7731', 'M', '1970-01-05', '016-332 4410', null],
            ['Wong Kar Yan', '920630-07-1144', 'F', '1992-06-30', '017-445 2290', 'Sulfonamides'],
            ['Siti Nurhaliza binti Omar', '990415-03-2208', 'F', '1999-04-15', '019-776 3301', null],
            ['Chong Wei Jie', '650817-01-5583', 'M', '1965-08-17', '012-301 9987', 'Penicillin'],
            ['Kavitha a/p Suresh', '881102-05-6612', 'F', '1988-11-02', '011-2093 4471', null],
            ['Mohd Hafiz bin Ismail', '010223-11-0915', 'M', '2001-02-23', '014-662 8810', null],
        ])->map(fn ($c) => Customer::query()->create([
            'name' => $c[0], 'ic_no' => $c[1], 'sex' => $c[2], 'dob' => $c[3], 'phone' => $c[4], 'allergies' => $c[5],
            'citizenship' => 'Malaysian', 'address' => mt_rand(1, 99).' Jalan '.['Ampang', 'Pudu', 'Bangsar', 'Cheras', 'Kepong'][mt_rand(0, 4)].', Kuala Lumpur',
        ]))->merge(Customer::query()->get());

        $products = Product::query()->get()->keyBy('name');
        $everyday = $products->filter(fn (Product $p) => $p->poison_group === PoisonGroup::None)->values();
        $pharmacyOnly = $products->filter(fn (Product $p) => in_array($p->poison_group, [PoisonGroup::C, PoisonGroup::D], true))->values();
        $prescription = $products->filter(fn (Product $p) => $p->poison_group->requiresPrescription())->values();

        // Day −40: opening stock for the new products, plus one batch that has since expired.
        $this->at($real->copy()->subDays(40), $owner, function () use ($products, $suppliers, $owner) {
            $lines = [];
            foreach ($products as $p) {
                if (Batch::query()->where('product_id', $p->id)->exists()) {
                    continue;
                }
                $lines[] = ['product_id' => $p->id, 'batch_no' => 'H'.$p->id.'A', 'expiry_date' => now()->addMonths(mt_rand(6, 20))->toDateString(), 'qty' => mt_rand(4, 12) * 10, 'cost_sen' => (int) ($p->price_sen * 0.62)];
            }
            $lines[] = ['product_id' => $products['Oral Rehydration Salts']->id, 'batch_no' => 'ORS-OLD', 'expiry_date' => now()->addDays(25)->toDateString(), 'qty' => 24, 'cost_sen' => 70];
            app(ReceiveGoods::class)->handle($owner, ['supplier_id' => $suppliers[1]->id, 'invoice_no' => 'APX-55120', 'received_on' => now()->toDateString(), 'payment_status' => 'paid', 'lines' => $lines]);
        });

        // Day −32: the monthly delivery from Zuellig, with a few short-dated batches.
        $this->at($real->copy()->subDays(32)->setTime(10, 0), $owner, function () use ($products, $owner) {
            $short = ['Strepsils' => 50, 'Piriton' => 70, 'Salonpas' => 85];
            app(ReceiveGoods::class)->handle($owner, [
                'supplier_id' => (int) Supplier::query()->where('name', 'like', 'Zuellig%')->value('id'),
                'invoice_no' => 'ZP-90233',
                'received_on' => now()->toDateString(),
                'payment_status' => 'paid',
                'lines' => array_values($products->values()->map(fn (Product $p) => [
                    'product_id' => $p->id,
                    'batch_no' => 'M'.$p->id.'B',
                    'expiry_date' => isset($short[$p->name]) ? now()->addDays(32 + $short[$p->name])->toDateString() : now()->addMonths(mt_rand(10, 24))->toDateString(),
                    'qty' => $p->price_sen < 300 ? 400 : 60,
                    'cost_sen' => (int) ($p->price_sen * 0.6),
                ])->all()),
            ]);
        });

        // Days −30 … −1: a shift a day for the pharmacist, an afternoon shift for the cashier on weekdays.
        $sales = [];
        for ($ago = self::DAYS; $ago >= 1; $ago--) {
            $day = $real->copy()->subDays($ago)->setTime(9, mt_rand(0, 20));
            $staff = [$pharmacist];
            if (! $day->isWeekend()) {
                $staff[] = $cashier;
            }

            foreach ($staff as $i => $user) {
                $start = $day->copy()->addHours($i * 5);
                $shift = $this->at($start, $user, fn () => Shift::query()->create(['branch_id' => $main->id, 'user_id' => $user->id, 'opening_float_sen' => 20000, 'opened_at' => now()]));

                for ($n = 0, $count = mt_rand(3, $day->isWeekend() ? 9 : 6); $n < $count; $n++) {
                    $when = $start->copy()->addMinutes(20 + $n * mt_rand(25, 40));
                    $sale = $this->at($when, $user, fn () => $this->randomSale($user, $everyday, $pharmacyOnly, $prescription, $customers));
                    if ($sale) {
                        $sales[] = $sale;
                    }
                }

                $this->at($start->copy()->addHours(4)->addMinutes(30), $user, function () use ($shift) {
                    $expected = $shift->cashSummary()['expected'];
                    $counted = $expected + [0, 0, 0, 0, -50, 100, -200][mt_rand(0, 6)];
                    $shift->update(['closed_at' => now(), 'expected_cash_sen' => $expected, 'counted_cash_sen' => $counted, 'note' => $counted === $expected ? null : 'Recounted twice']);
                });
            }

            // A few refunds, account payments and back-office jobs scattered through the month.
            if ($ago % 6 === 0 && $sales) {
                $sale = $sales[array_rand($sales)];
                $this->at($day->copy()->setTime(13, 15), $pharmacist, function () use ($sale, $pharmacist, $main) {
                    $line = $sale->lines()->first();
                    if ($line && $line->qty > $line->refunded_qty) {
                        $shift = Shift::query()->create(['branch_id' => $main->id, 'user_id' => $pharmacist->id, 'opening_float_sen' => 0, 'opened_at' => now()]);
                        app(RefundSale::class)->handle($pharmacist, $sale, [$line->id => 1], ['Wrong strength dispensed', 'Customer returned unopened', 'Duplicate purchase'][mt_rand(0, 2)]);
                        $shift->update(['closed_at' => now()->addMinute(), 'expected_cash_sen' => $shift->cashSummary()['expected'], 'counted_cash_sen' => $shift->cashSummary()['expected']]);
                    }
                });
            }
            if ($ago % 9 === 0) {
                $this->at($day->copy()->setTime(16, 40), $pharmacist, function () use ($customers, $main, $pharmacist) {
                    foreach ($customers as $c) {
                        $owed = $c->balanceSen();
                        if ($owed > 0) {
                            CustomerPayment::query()->create(['customer_id' => $c->id, 'branch_id' => $main->id, 'user_id' => $pharmacist->id, 'amount_sen' => intdiv($owed, 2) ?: $owed, 'method' => 'bank', 'reference' => 'IBG'.mt_rand(100000, 999999)]);
                        }
                    }
                });
            }
        }

        // Purchase orders: one received against a delivery, one sent, one draft, one cancelled.
        $this->at($real->copy()->subDays(12)->setTime(10, 0), $assistant, function () use ($main, $suppliers, $assistant, $owner) {
            $po = $this->order($main, $suppliers[0], $assistant, [['Glucophage', 300, 28], ['Norvasc', 120, 121], ['Lipitor', 90, 210]], 'ordered');
            Carbon::setTestNow(now()->addDays(3));
            app(ReceiveGoods::class)->handle($owner, [
                'supplier_id' => $suppliers[0]->id, 'purchase_order_id' => $po->id, 'invoice_no' => 'DKSH-778201', 'received_on' => now()->toDateString(), 'payment_status' => 'partial',
                'lines' => array_values($po->lines->map(fn (PurchaseOrderLine $l) => ['product_id' => $l->product_id, 'batch_no' => 'D'.$l->product_id.'X', 'expiry_date' => now()->addMonths(18)->toDateString(), 'qty' => $l->qty, 'cost_sen' => $l->cost_sen])->all()),
            ]);
        });
        $this->at($real->copy()->subDays(3)->setTime(11, 0), $pharmacist, fn () => $this->order($main, $suppliers[2], $pharmacist, [['Accu-Chek Test Strips', 10, 4900], ['Omron Face Mask', 20, 1200]], 'ordered'));
        $this->at($real->copy()->subDays(8)->setTime(11, 0), $assistant, fn () => $this->order($main, $suppliers[1], $assistant, [['Gaviscon Double Action', 12, 1500]], 'cancelled'));
        $this->at($real->copy()->subDay()->setTime(17, 0), $assistant, fn () => $this->order($main, $suppliers[1], $assistant, [['Salonpas', 24, 600], ['Dettol Antiseptic', 12, 820], ['Eurax Cream', 6, 990]], 'draft'));

        // Stock take and write-offs.
        $this->at($real->copy()->subDays(2)->setTime(18, 30), $pharmacist, function () use ($pharmacist, $products) {
            $adjust = app(AdjustStock::class);
            $old = Batch::query()->where('batch_no', 'ORS-OLD')->firstOrFail();
            $left = (int) $old->stockLevels()->sum('qty');
            if ($left > 0) {
                $adjust->handle($pharmacist, $old, -$left, 'expired', 'Expired on shelf');
            }
            $dettol = Batch::query()->where('product_id', $products['Dettol Antiseptic']->id)->firstOrFail();
            $adjust->handle($pharmacist, $dettol, -1, 'damaged', 'Bottle cracked in delivery');
            $masks = Batch::query()->where('product_id', $products['Omron Face Mask']->id)->firstOrFail();
            $adjust->handle($pharmacist, $masks, -2, 'count', 'Monthly stock take');
        });

        // A second outlet, its staff, and a transfer to stock it.
        $bangsar = Branch::query()->create([
            'name' => 'Farmasi Seri Mutiara Bangsar', 'company_name' => $main->company_name, 'licence_no' => 'A/12388', 'address' => '27 Jalan Telawi 3, Bangsar Baru',
            'postcode' => '59100', 'city' => 'Kuala Lumpur', 'state' => '14', 'phone' => '03-2283 0000', 'tin' => $main->tin, 'brn' => $main->brn, 'msic_code' => $main->msic_code,
        ]);
        User::factory()->create(['name' => 'Nurul Ain', 'email' => 'bangsar@pharmacy.test', 'password' => 'Zx123456', 'branch_id' => $bangsar->id])->assignRole('pharmacist');
        $this->at($real->copy()->subDays(5)->setTime(9, 30), $owner, function () use ($owner, $bangsar, $products) {
            $lines = collect(['Panadol', 'Blackmores Vitamin C', 'Zyrtec', 'Salonpas', 'Oral Rehydration Salts'])
                ->map(fn ($name) => Batch::query()->where('product_id', $products[$name]->id)->whereHas('stockLevels', fn ($q) => $q->where('branch_id', $owner->branch_id)->where('qty', '>=', 20))->orderByDesc('expiry_date')->first())
                ->filter()
                ->map(fn (Batch $b) => ['batch_id' => $b->id, 'qty' => 10])
                ->values()
                ->all();
            app(TransferStock::class)->handle($owner, $bangsar, array_values($lines), 'Opening stock for Bangsar');
        });

        $this->einvoices($main, $real, $owner);

        Carbon::setTestNow();
        Auth::logout();
    }

    /**
     * Run $fn as $user with the clock at $when.
     *
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    private function at(CarbonInterface $when, User $user, callable $fn): mixed
    {
        Carbon::setTestNow($when);
        Auth::setUser($user);

        return $fn();
    }

    /**
     * @param  Collection<int, Product>  $everyday
     * @param  Collection<int, Product>  $pharmacyOnly
     * @param  Collection<int, Product>  $prescription
     * @param  Collection<int, Customer>  $customers
     */
    private function randomSale(User $user, $everyday, $pharmacyOnly, $prescription, $customers): ?Sale
    {
        $lines = [];
        foreach ($everyday->random(mt_rand(1, 3)) as $p) {
            $lines[$p->id] = ['product_id' => $p->id, 'qty' => mt_rand(1, $p->price_sen < 200 ? 20 : 2)];
        }

        $data = ['payment_method' => ['cash', 'cash', 'card', 'ewallet'][mt_rand(0, 3)]];
        $roll = mt_rand(1, 10);

        if ($user->isPharmacist() && $roll >= 7) {
            $customer = $customers->random();
            $data['customer_id'] = $customer->id;
            if ($roll >= 9) {
                $p = $prescription->random();
                $lines[$p->id] = ['product_id' => $p->id, 'qty' => $p->unit === 'inhaler' ? 1 : mt_rand(2, 6) * 7, 'dosage' => ['1 tablet once daily', '1 tablet twice daily after food', '2 puffs when needed', '1 capsule 3 times a day'][mt_rand(0, 3)]];
                $data['prescription'] = ['prescriber_name' => ['Dr. Tan Mei Ling', 'Dr. Ravi Shankar', 'Dr. Nor Azizah', 'Dr. Lim Chee Keong'][mt_rand(0, 3)], 'prescriber_reg_no' => 'MMC '.mt_rand(30000, 69999), 'clinic' => ['Klinik Harmoni', 'Poliklinik Bangsar', 'Klinik Keluarga Pudu'][mt_rand(0, 2)], 'issued_on' => now()->toDateString(), 'refills_allowed' => mt_rand(0, 2)];
                if ($roll === 10) {
                    $data['payment_method'] = 'credit';
                }
            } else {
                $p = $pharmacyOnly->random();
                $lines[$p->id] = ['product_id' => $p->id, 'qty' => mt_rand(1, 10), 'dosage' => 'As directed by pharmacist'];
            }
        }

        $data['lines'] = array_values($lines);
        $total = collect($data['lines'])->sum(fn ($l) => $l['qty'] * Product::query()->find($l['product_id'])->price_sen);
        if ($data['payment_method'] === 'cash') {
            $data['tendered_sen'] = (int) (ceil($total / 1000) * 1000);
        }
        if (mt_rand(1, 12) === 1) {
            $data['discount_sen'] = intdiv($total, 20);
        }

        try {
            return app(CompleteSale::class)->handle($user, $data);
        } catch (ValidationException) {
            return null; // ran out of a batch; skip like a real counter would
        }
    }

    /**
     * @param  list<array{string, int, int}>  $lines  [product name, qty, cost sen]
     */
    private function order(Branch $branch, Supplier $supplier, User $user, array $lines, string $status): PurchaseOrder
    {
        $po = PurchaseOrder::query()->create(['branch_id' => $branch->id, 'supplier_id' => $supplier->id, 'number' => 'tmp'.uniqid(), 'status' => $status === 'cancelled' ? 'cancelled' : $status, 'user_id' => $user->id, 'expected_on' => now()->addDays(4)->toDateString(), 'note' => 'Please deliver before noon']);
        $po->update(['number' => sprintf('PO%s-%05d', now()->format('ym'), $po->id)]);
        $po->lines()->createMany(array_map(fn ($l) => ['product_id' => Product::query()->where('name', $l[0])->value('id'), 'qty' => $l[1], 'cost_sen' => $l[2]], $lines));

        return $po->load('lines');
    }

    /**
     * Sandbox e-invoices through the fake driver only: never call LHDN from a seeder.
     */
    private function einvoices(Branch $main, CarbonInterface $real, User $owner): void
    {
        if (config('einvoice.driver') !== 'fake') {
            return;
        }

        $main->update(['tin' => 'C12345678901', 'brn' => '202101001341']);
        EInvoiceSetting::query()->create(['tin' => 'C12345678901', 'environment' => 'sandbox', 'client_id' => 'demo-client', 'client_secret' => 'demo-secret', 'unsigned' => true])->activate();
        $einvoice = app(EInvoice::class);

        $this->at($real->copy()->subDays(4)->setTime(15, 0), $owner, function () use ($einvoice, $main) {
            $klinik = Customer::query()->where('name', 'Klinik Harmoni Sdn Bhd')->firstOrFail();
            $user = User::query()->where('email', 'pharmacist@pharmacy.test')->firstOrFail();
            $shift = Shift::query()->create(['branch_id' => $main->id, 'user_id' => $user->id, 'opening_float_sen' => 0, 'opened_at' => now()]);
            $sale = app(CompleteSale::class)->handle($user, ['customer_id' => $klinik->id, 'lines' => [['product_id' => Product::query()->where('name', 'Omron Face Mask')->value('id'), 'qty' => 5], ['product_id' => Product::query()->where('name', 'Dettol Antiseptic')->value('id'), 'qty' => 3]], 'payment_method' => 'card']);
            $shift->update(['closed_at' => now()->addMinutes(5), 'expected_cash_sen' => 0, 'counted_cash_sen' => 0]);
            $einvoice->poll($einvoice->submit($sale)->refresh());
        });

        $previous = $real->copy()->subMonthNoOverflow()->format('Y-m');
        $this->at($real->copy()->startOfMonth()->addDays(2)->setTime(10, 0), $owner, function () use ($einvoice, $main, $previous) {
            $batch = ConsolidatedEinvoice::query()->create(['branch_id' => $main->id, 'period' => $previous, 'sale_count' => 0, 'subtotal_sen' => 0, 'tax_sen' => 0, 'total_sen' => 0])->recalculate();
            if ($batch->sale_count > 0) {
                $einvoice->poll($einvoice->submit($batch)->refresh());
            }
        });

    }
}
