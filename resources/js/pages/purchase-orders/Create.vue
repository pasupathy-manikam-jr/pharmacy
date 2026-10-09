<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, ShoppingCart, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Pharmacy/PurchaseOrderController';
import Combobox from '@/components/Combobox.vue';
import DatePicker from '@/components/DatePicker.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fromSen, rm, toSen } from '@/lib/money';
import { index } from '@/routes/purchase-orders';
import type { Option } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Purchase orders', href: index() },
            { title: 'New order', href: '#' },
        ],
    },
});

const props = defineProps<{
    suppliers: { id: number; name: string }[];
    products: { id: number; name: string; strength: string | null }[];
    suggested: { product_id: number; qty: number; cost_sen: number }[];
}>();

const supplierOptions = computed<Option[]>(() =>
    props.suppliers.map((s) => ({ value: s.id, label: s.name })),
);
const productOptions = computed<Option[]>(() =>
    props.products.map((p) => ({
        value: p.id,
        label: `${p.name} ${p.strength ?? ''}`.trim(),
    })),
);

type Line = { product_id: number | null; qty: string; cost: string };
const blank = (): Line => ({ product_id: null, qty: '', cost: '' });

const form = useForm({
    supplier_id: null as number | null,
    expected_on: null as string | null,
    note: '',
    lines: props.suggested.length
        ? props.suggested.map((s) => ({
              product_id: s.product_id,
              qty: String(s.qty),
              cost: s.cost_sen ? fromSen(s.cost_sen) : '',
          }))
        : [blank()],
});

const total = computed(() =>
    form.lines.reduce(
        (s, l) => s + toSen(l.cost) * (Number.parseInt(l.qty, 10) || 0),
        0,
    ),
);
const err = (i: number, f: string) =>
    (form.errors as Record<string, string>)[`lines.${i}.${f}`];

const submit = () =>
    form
        .transform((d) => ({
            ...d,
            note: d.note || null,
            lines: d.lines.map((l) => ({
                product_id: l.product_id,
                qty: Number.parseInt(l.qty || '0', 10),
                cost_sen: toSen(l.cost),
            })),
        }))
        .post(PurchaseOrderController.store.url());
</script>

<template>
    <Head title="New purchase order" />

    <form
        class="flex flex-col gap-5 p-4 md:p-6"
        novalidate
        @submit.prevent="submit"
    >
        <PageHeader
            :back="{ href: index(), label: 'purchase orders' }"
            title="New purchase order"
            :description="
                suggested.length
                    ? `Started with the ${suggested.length} products at or below their reorder level.`
                    : 'Add the products you want to order.'
            "
            :icon="ShoppingCart"
            tone="bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300"
        />

        <div class="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-3">
            <div class="grid gap-1.5">
                <Label for="supplier">Supplier</Label>
                <Combobox
                    id="supplier"
                    v-model="form.supplier_id"
                    :options="supplierOptions"
                    placeholder="Choose supplier"
                />
                <InputError :message="form.errors.supplier_id" />
            </div>
            <div class="grid gap-1.5">
                <Label for="expected">Expected delivery</Label>
                <DatePicker
                    id="expected"
                    v-model="form.expected_on"
                    placeholder="Optional"
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="note">Note to supplier</Label>
                <Input id="note" v-model="form.note" />
            </div>
        </div>

        <div class="rounded-xl border bg-card p-5">
            <div
                class="hidden grid-cols-[2fr_0.6fr_0.8fr_auto] gap-3 pb-2 text-sm font-medium text-muted-foreground md:grid"
            >
                <span>Product</span><span>Qty</span><span>Unit cost (RM)</span
                ><span class="w-9" />
            </div>
            <div
                v-for="(line, i) in form.lines"
                :key="i"
                class="grid gap-3 border-t py-3 first:border-t-0 md:grid-cols-[2fr_0.6fr_0.8fr_auto]"
            >
                <div>
                    <Combobox
                        v-model="line.product_id"
                        :options="productOptions"
                        placeholder="Choose product"
                    />
                    <InputError :message="err(i, 'product_id')" />
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
                    ><Trash2 class="text-rose-500"
                /></Button>
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
                        class="bg-blue-600 hover:bg-blue-700"
                        >Save order</Button
                    >
                </div>
            </div>
        </div>
    </form>
</template>
