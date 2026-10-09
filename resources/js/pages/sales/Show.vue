<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Printer, RotateCcw, ScanBarcode } from '@lucide/vue';
import { computed, ref } from 'vue';
import EInvoiceController from '@/actions/App/Http/Controllers/Pharmacy/EInvoiceController';
import SaleController from '@/actions/App/Http/Controllers/Pharmacy/SaleController';
import type { EInvoiceSummary } from '@/components/EInvoicePanel.vue';
import EInvoicePanel from '@/components/EInvoicePanel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate, formatDateTime, rm } from '@/lib/money';
import { index as pos } from '@/routes/pos';
import { index } from '@/routes/sales';
import type { Branch, Customer, PoisonGroup } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sales', href: index() },
            { title: 'Receipt', href: '#' },
        ],
    },
});

type Line = {
    id: number;
    qty: number;
    refunded_qty: number;
    price_sen: number;
    dosage: string | null;
    product: {
        name: string;
        strength: string | null;
        unit: string;
        poison_group: PoisonGroup;
    };
    batch: { batch_no: string; expiry_date: string };
};
type RefundRow = {
    id: number;
    number: string;
    amount_sen: number;
    reason: string;
    created_at: string;
    user: { name: string };
};
type Sale = {
    id: number;
    number: string;
    created_at: string;
    subtotal_sen: number;
    discount_sen: number;
    tax_sen: number;
    total_sen: number;
    tendered_sen: number;
    payment_method: string;
    status: 'completed' | 'partially_refunded' | 'refunded';
    branch: Branch;
    user: { name: string };
    customer: Customer | null;
    prescription: {
        prescriber_name: string;
        prescriber_reg_no: string | null;
    } | null;
    lines: Line[];
    refunds: RefundRow[];
};

const props = defineProps<{
    sale: Sale;
    lineNet: Record<number, number>;
    canRefund: boolean;
    einvoice: EInvoiceSummary | null;
    refundEinvoices: Record<number, EInvoiceSummary | null>;
}>();

const print = () => window.print();
const methodLabel: Record<string, string> = {
    cash: 'Cash',
    card: 'Card',
    ewallet: 'E-wallet',
    credit: 'On account',
};

const refundable = computed(() =>
    props.sale.lines.filter((l) => l.qty > l.refunded_qty),
);
const open = ref(false);
const form = useForm({ lines: {} as Record<number, string>, reason: '' });

function startRefund() {
    form.reset();
    form.clearErrors();
    form.lines = Object.fromEntries(
        refundable.value.map((l) => [l.id, String(l.qty - l.refunded_qty)]),
    );
    open.value = true;
}

const estimate = computed(() =>
    refundable.value.reduce((sum, l) => {
        const q = Math.min(
            Number.parseInt(form.lines[l.id] || '0', 10) || 0,
            l.qty - l.refunded_qty,
        );
        return sum + Math.floor(((props.lineNet[l.id] ?? 0) * q) / l.qty);
    }, 0),
);

const refund = () =>
    form
        .transform((d) => ({
            reason: d.reason,
            lines: Object.fromEntries(
                Object.entries(d.lines).map(([id, q]) => [
                    id,
                    Number.parseInt(q || '0', 10) || 0,
                ]),
            ),
        }))
        .post(SaleController.refund.url(props.sale.id), {
            preserveScroll: true,
            onSuccess: () => (open.value = false),
        });

const errors = computed(() => form.errors as Record<string, string>);
</script>

<template>
    <Head :title="sale.number" />

    <div class="flex flex-col items-center gap-5 p-4 md:p-6">
        <div
            class="no-print flex w-full max-w-xl flex-wrap justify-center gap-2"
        >
            <Button class="bg-sky-600 hover:bg-sky-700" @click="print"
                ><Printer /> Print receipt</Button
            >
            <Button variant="outline" as-child
                ><Link :href="pos()"><ScanBarcode /> New sale</Link></Button
            >
            <Button
                v-if="canRefund && refundable.length"
                variant="outline"
                class="text-rose-600"
                @click="startRefund"
                ><RotateCcw /> Refund items</Button
            >
        </div>

        <div
            v-if="canRefund"
            class="no-print w-full max-w-xl rounded-xl border bg-card p-3"
        >
            <EInvoicePanel
                :einvoice="einvoice"
                :submit-url="
                    sale.customer?.tin
                        ? EInvoiceController.submitSale.url(sale.id)
                        : undefined
                "
                submit-label="Issue e-invoice to customer"
            />
            <p
                v-if="!einvoice && !sale.customer?.tin"
                class="mt-1 text-xs text-muted-foreground"
            >
                Walk-in sales go into the monthly consolidated e-invoice. Add
                the customer’s TIN to issue them their own.
            </p>
        </div>

        <!-- 80mm thermal receipt -->
        <article
            class="w-[80mm] max-w-full bg-white p-4 font-mono text-[12px] leading-snug text-black shadow-sm ring-1 ring-black/5 print:shadow-none print:ring-0"
        >
            <header class="text-center">
                <p class="text-sm font-bold">{{ sale.branch.name }}</p>
                <p v-if="sale.branch.address">{{ sale.branch.address }}</p>
                <p v-if="sale.branch.phone">Tel {{ sale.branch.phone }}</p>
                <p v-if="sale.branch.licence_no">
                    Licence {{ sale.branch.licence_no }}
                </p>
            </header>
            <div class="my-2 border-t border-dashed border-black" />
            <p>{{ sale.number }}</p>
            <p>{{ formatDateTime(sale.created_at) }}, {{ sale.user.name }}</p>
            <p v-if="sale.customer">Customer: {{ sale.customer.name }}</p>
            <p v-if="sale.prescription">
                Rx: {{ sale.prescription.prescriber_name }}
                {{ sale.prescription.prescriber_reg_no }}
            </p>
            <div class="my-2 border-t border-dashed border-black" />
            <div v-for="l in sale.lines" :key="l.id" class="mb-1.5">
                <p>{{ l.product.name }} {{ l.product.strength }}</p>
                <div class="flex justify-between">
                    <span>{{ l.qty }} × {{ rm(l.price_sen) }}</span>
                    <span>{{ rm(l.qty * l.price_sen) }}</span>
                </div>
                <p class="text-[10px]">
                    Batch {{ l.batch.batch_no }} exp
                    {{ formatDate(l.batch.expiry_date) }}
                </p>
                <p v-if="l.dosage" class="font-bold">{{ l.dosage }}</p>
                <p v-if="l.refunded_qty" class="text-[10px]">
                    Refunded {{ l.refunded_qty }}
                </p>
            </div>
            <div class="my-2 border-t border-dashed border-black" />
            <div class="flex justify-between">
                <span>Subtotal</span><span>{{ rm(sale.subtotal_sen) }}</span>
            </div>
            <div v-if="sale.tax_sen" class="flex justify-between">
                <span>Tax</span><span>{{ rm(sale.tax_sen) }}</span>
            </div>
            <div v-if="sale.discount_sen" class="flex justify-between">
                <span>Discount</span><span>−{{ rm(sale.discount_sen) }}</span>
            </div>
            <div class="flex justify-between text-sm font-bold">
                <span>TOTAL</span><span>{{ rm(sale.total_sen) }}</span>
            </div>
            <div class="flex justify-between">
                <span>{{ methodLabel[sale.payment_method] }}</span
                ><span>{{ rm(sale.tendered_sen) }}</span>
            </div>
            <div
                v-if="sale.tendered_sen > sale.total_sen"
                class="flex justify-between"
            >
                <span>Change</span
                ><span>{{ rm(sale.tendered_sen - sale.total_sen) }}</span>
            </div>
            <template v-if="sale.refunds.length">
                <div class="my-2 border-t border-dashed border-black" />
                <div
                    v-for="r in sale.refunds"
                    :key="r.id"
                    class="flex justify-between"
                >
                    <span>Refund {{ r.number }}</span
                    ><span>−{{ rm(r.amount_sen) }}</span>
                </div>
            </template>
            <p class="mt-3 text-center">Thank you. Get well soon.</p>
        </article>

        <section
            v-if="sale.refunds.length"
            class="no-print w-full max-w-xl rounded-xl border bg-card p-4"
        >
            <h2 class="mb-2 font-semibold">Refunds</h2>
            <div
                v-for="r in sale.refunds"
                :key="r.id"
                class="flex flex-col gap-1 border-t py-2 first:border-t-0"
            >
                <div class="flex justify-between text-sm">
                    <span
                        ><b>{{ r.number }}</b> by {{ r.user.name }},
                        {{ formatDateTime(r.created_at) }}</span
                    >
                    <span class="font-semibold text-rose-600"
                        >−{{ rm(r.amount_sen) }}</span
                    >
                </div>
                <p class="text-sm text-muted-foreground">{{ r.reason }}</p>
                <EInvoicePanel
                    v-if="einvoice?.status === 'valid' || refundEinvoices[r.id]"
                    :einvoice="refundEinvoices[r.id] ?? null"
                    :submit-url="EInvoiceController.submitRefund.url(r.id)"
                    submit-label="Send refund note"
                />
            </div>
        </section>

        <Dialog v-model:open="open">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle
                        >Refund items from {{ sale.number }}</DialogTitle
                    >
                    <DialogDescription
                        >Items go back into stock. Money is returned the way it
                        was paid ({{
                            methodLabel[sale.payment_method]
                        }}).</DialogDescription
                    >
                </DialogHeader>
                <form class="grid gap-4" novalidate @submit.prevent="refund">
                    <div
                        v-for="l in refundable"
                        :key="l.id"
                        class="grid grid-cols-[1fr_5rem] items-center gap-3"
                    >
                        <div>
                            <p class="font-medium">
                                {{ l.product.name }} {{ l.product.strength }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Up to {{ l.qty - l.refunded_qty }}
                                {{ l.product.unit }}
                            </p>
                            <InputError :message="errors[`lines.${l.id}`]" />
                        </div>
                        <Input
                            v-model="form.lines[l.id]"
                            inputmode="numeric"
                            :aria-label="`Quantity of ${l.product.name} to refund`"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="reason">Reason</Label>
                        <Input
                            id="reason"
                            v-model="form.reason"
                            placeholder="e.g. Wrong strength dispensed"
                        />
                        <InputError :message="form.errors.reason" />
                    </div>
                    <InputError :message="errors.lines ?? errors.shift" />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="open = false"
                            >Keep sale</Button
                        >
                        <Button
                            variant="destructive"
                            :disabled="form.processing"
                            >Refund {{ rm(estimate) }}</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
