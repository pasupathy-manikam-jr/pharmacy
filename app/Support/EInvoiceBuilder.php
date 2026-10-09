<?php

namespace App\Support;

use App\Models\Branch;
use EInvoiceSdk\Data\LineItem;
use EInvoiceSdk\Data\Party;
use EInvoiceSdk\Data\Tax;
use EInvoiceSdk\Exceptions\EInvoiceException;

/**
 * Shared pieces of the LHDN documents built from sales, refunds and consolidated batches. Amounts arrive in sen.
 */
class EInvoiceBuilder
{
    /** LHDN classification for general goods, and for consolidated B2C lines. */
    public const CLASS_GOODS = '022';

    public const CLASS_CONSOLIDATED = '004';

    /** Buyer TIN LHDN prescribes for the general public in consolidated e-invoices. */
    public const GENERAL_PUBLIC_TIN = 'EI00000000010';

    public static function supplier(Branch $branch): Party
    {
        if (! $branch->tin) {
            throw new EInvoiceException(__('Add the company TIN under Branch settings before sending e-invoices.'));
        }

        return new Party(
            name: $branch->company_name ?: $branch->name,
            tin: $branch->tin,
            brn: $branch->brn,
            sstNumber: $branch->sst_no,
            email: $branch->email,
            phone: $branch->phone,
            addressLine1: $branch->address,
            postcode: $branch->postcode,
            city: $branch->city,
            state: $branch->state,
            msicCode: $branch->msic_code,
            msicDescription: 'Retail sale of pharmaceutical and medical goods',
        );
    }

    public static function generalPublic(): Party
    {
        return new Party(name: 'General Public', tin: self::GENERAL_PUBLIC_TIN);
    }

    /**
     * Group taxes by rate; untaxed goods are reported as "not applicable" (06).
     *
     * @param  list<array{taxable_sen: int, tax_sen: int, rate_bp: int}>  $parts
     * @return list<Tax>
     */
    public static function taxes(array $parts): array
    {
        $byRate = [];
        foreach ($parts as $p) {
            $byRate[$p['rate_bp']] ??= ['taxable' => 0, 'tax' => 0];
            $byRate[$p['rate_bp']]['taxable'] += $p['taxable_sen'];
            $byRate[$p['rate_bp']]['tax'] += $p['tax_sen'];
        }

        $taxes = [];
        foreach ($byRate as $rateBp => $sum) {
            $taxes[] = $rateBp > 0
                ? new Tax(code: '01', amount: $sum['tax'] / 100, taxableAmount: $sum['taxable'] / 100, rate: $rateBp / 100)
                : new Tax(code: '06', amount: 0, taxableAmount: $sum['taxable'] / 100);
        }

        return $taxes ?: [new Tax(code: '06', amount: 0, taxableAmount: 0)];
    }

    /**
     * @param  list<Tax>  $taxes
     */
    public static function line(string $description, int $qty, int $unitSen, int $discountSen, array $taxes, string $classification = self::CLASS_GOODS): LineItem
    {
        return new LineItem(
            description: $description,
            quantity: $qty,
            unitPrice: $unitSen / 100,
            subtotal: ($unitSen * $qty - $discountSen) / 100,
            classificationCodes: [$classification],
            taxes: $taxes,
            unit: 'C62',
            discount: $discountSen / 100,
        );
    }

    /**
     * Split $total across $weights in proportion, with the rounding remainder on the last share, so shares sum exactly.
     *
     * @param  list<int>  $weights
     * @return list<int>
     */
    public static function allocate(int $total, array $weights): array
    {
        $sum = array_sum($weights);
        if ($sum === 0 || $total === 0) {
            return array_fill(0, count($weights), 0);
        }

        $shares = [];
        $given = 0;
        $running = 0;
        foreach ($weights as $w) {
            $running += $w;
            $upTo = intdiv($total * $running, $sum);
            $shares[] = $upTo - $given;
            $given = $upTo;
        }

        return $shares;
    }
}
