<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ClipboardList, Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import GoodsReceiptController from '@/actions/App/Http/Controllers/Pharmacy/GoodsReceiptController';
import Combobox from '@/components/Combobox.vue';
import DatePicker from '@/components/DatePicker.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { fromSen, rm, toSen } from '@/lib/money';
import { index } from '@/routes/receipts';
import type { Option } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Goods received', href: index() },
            { title: 'Receive stock', href: '#' },
        ],
    },
});

const props = defineProps<{
    order: {
        id: number;
        number: string;
        supplier_id: number;
        lines: { product_id: number; qty: number; cost_sen: number }[];
    } | null;
    suppliers: { id: number; name: string }[];
    products: {
        id: number;
        name: string;
        strength: string | null;
        barcode: string | null;
    }[];
}>();

const supplierOptions = computed<Option[]>(() =>
    props.suppliers.map((s) => ({ value: s.id, label: s.name })),
);
const productOptions = computed<Option[]>(() =>
    props.products.map((p) => ({
        value: p.id,
        label: `${p.name} ${p.strength ?? ''}`.trim(),
        hint: p.barcode ?? undefined,
    })),
);

type Line = {
    product_id: number | null;
    batch_no: string;
    expiry_date: string | null;
    qty: string;
    cost: string;
};
const blank = (): Line => ({
    product_id: null,
    batch_no: '',
    expiry_date: null,
    qty: '',
    cost: '',
});

const form = useForm({
    purchase_order_id: props.order?.id ?? null,
    supplier_id: (props.order?.supplier_id ?? null) as number | null,
    invoice_no: '',
    received_on: new Date().toISOString().slice(0, 10) as string | null,
    payment_status: 'pending',
    lines: props.order
        ? props.order.lines.map((l) => ({
              ...blank(),
              product_id: l.product_id,
              qty: String(l.qty),
              cost: fromSen(l.cost_sen),
          }))
        : [blank()],
});

const total = computed(() =>
    form.lines.reduce(
        (sum, l) => sum + toSen(l.cost) * (Number.parseInt(l.qty, 10) || 0),
        0,
    ),
);

const err = (i: number, field: string) =>
    (form.errors as Record<string, string>)[`lines.${i}.${field}`];

function submit() {
    form.transform((data) => ({
        ...data,
        lines: data.lines.map((l) => ({
            product_id: l.product_id,
            batch_no: l.batch_no,
            expiry_date: l.expiry_date,
            qty: Number.parseInt(l.qty, 10) || 0,
            cost_sen: toSen(l.cost),
        })),
    })).post(GoodsReceiptController.store.url());
}
</script>

<template>
    <Head title="Receive stock" />

    <form
        class="flex flex-col gap-5 p-4 md:p-6"
        novalidate
        @submit.prevent="submit"
    >
        <PageHeader
            title="Receive stock"
            :description="
                order
                    ? `Against ${order.number}. Enter each batch exactly as printed on the box.`
                    : 'Enter each batch exactly as printed on the box.'
            "
            :icon="ClipboardList"
            tone="bg-lime-100 text-lime-700 dark:bg-lime-500/20 dark:text-lime-300"
        />

        <div class="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-4">
            <div class="grid gap-1.5">
                <Label for="supplier">Supplier</Label>
                <Combobox
                    id="supplier"
                    v-model="form.supplier_id"
                    :options="supplierOptions"
                    placeholder="Choose supplier"
                    search-placeholder="Search suppliers"
                    empty-text="No supplier found. Add one under Suppliers."
                />
                <InputError :message="form.errors.supplier_id" />
            </div>
            <div class="grid gap-1.5">
                <Label for="invoice_no">Supplier invoice no.</Label>
                <Input id="invoice_no" v-model="form.invoice_no" />
            </div>
            <div class="grid gap-1.5">
                <Label for="received_on">Received on</Label>
                <DatePicker id="received_on" v-model="form.received_on" />
                <InputError :message="form.errors.received_on" />
            </div>
            <div class="grid gap-1.5">
                <Label for="payment_status">Payment</Label>
                <Select v-model="form.payment_status">
                    <SelectTrigger id="payment_status" class="w-full"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="pending">Not paid yet</SelectItem>
                        <SelectItem value="partial">Partly paid</SelectItem>
                        <SelectItem value="paid">Paid</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <div class="rounded-xl border bg-card p-5">
            <div
                class="hidden grid-cols-[2fr_1fr_1fr_0.6fr_0.8fr_auto] gap-3 pb-2 text-sm font-medium text-muted-foreground md:grid"
            >
                <span>Product</span><span>Batch no.</span><span>Expiry</span
                ><span>Qty</span><span>Unit cost (RM)</span><span class="w-9" />
            </div>
            <div
                v-for="(line, i) in form.lines"
                :key="i"
                class="grid gap-3 border-t py-3 first:border-t-0 md:grid-cols-[2fr_1fr_1fr_0.6fr_0.8fr_auto]"
            >
                <div>
                    <Combobox
                        v-model="line.product_id"
                        :options="productOptions"
                        placeholder="Choose product"
                        search-placeholder="Name or barcode"
                    />
                    <InputError :message="err(i, 'product_id')" />
                </div>
                <div>
                    <Input v-model="line.batch_no" placeholder="Batch no." />
                    <InputError :message="err(i, 'batch_no')" />
                </div>
                <div>
                    <DatePicker
                        v-model="line.expiry_date"
                        placeholder="Expiry"
                    />
                    <InputError :message="err(i, 'expiry_date')" />
                </div>
                <div>
                    <Input
                        v-model="line.qty"
                        inputmode="numeric"
                        placeholder="0"
                    />
                    <InputError :message="err(i, 'qty')" />
                </div>
                <div>
                    <Input
                        v-model="line.cost"
                        inputmode="decimal"
                        placeholder="0.00"
                    />
                    <InputError :message="err(i, 'cost_sen')" />
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    :disabled="form.lines.length === 1"
                    aria-label="Remove line"
                    @click="form.lines.splice(i, 1)"
                >
                    <Trash2 class="text-rose-500" />
                </Button>
            </div>
            <InputError :message="form.errors.lines" />
            <div
                class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t pt-4"
            >
                <Button
                    type="button"
                    variant="outline"
                    @click="form.lines.push(blank())"
                    ><Plus /> Add line</Button
                >
                <div class="flex items-center gap-4">
                    <span class="text-muted-foreground"
                        >Total
                        <b
                            class="tabular font-display text-xl text-foreground"
                            >{{ rm(total) }}</b
                        ></span
                    >
                    <Button
                        :disabled="form.processing"
                        class="bg-lime-600 hover:bg-lime-700"
                        >Receive stock</Button
                    >
                </div>
            </div>
        </div>
    </form>
</template>
