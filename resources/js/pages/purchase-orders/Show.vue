<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { PackageOpen, Printer, Send, XCircle } from '@lucide/vue';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Pharmacy/PurchaseOrderController';
import BackButton from '@/components/BackButton.vue';
import { Button } from '@/components/ui/button';
import { formatDate, rm } from '@/lib/money';
import { index } from '@/routes/purchase-orders';
import { create as receive } from '@/routes/receipts';
import type { Branch, Supplier } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Purchase orders', href: index() },
            { title: 'Order', href: '#' },
        ],
    },
});

type Order = {
    id: number;
    number: string;
    status: 'draft' | 'ordered' | 'received' | 'cancelled';
    created_at: string;
    expected_on: string | null;
    note: string | null;
    supplier: Supplier;
    branch: Branch & { company_name: string | null };
    user: { name: string };
    lines: {
        id: number;
        qty: number;
        cost_sen: number;
        product: { name: string; strength: string | null; unit: string };
    }[];
};

const props = defineProps<{ order: Order }>();
const print = () => window.print();
const setStatus = (status: string) =>
    router.post(
        PurchaseOrderController.status.url(props.order.id),
        { status },
        { preserveScroll: true },
    );
const total = () =>
    props.order.lines.reduce((s, l) => s + l.qty * l.cost_sen, 0);
</script>

<template>
    <Head :title="order.number" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <div class="no-print flex flex-wrap gap-2">
            <BackButton :href="index()" label="purchase orders" />
            <Button
                v-if="order.status === 'draft'"
                class="bg-blue-600 hover:bg-blue-700"
                @click="setStatus('ordered')"
                ><Send /> Mark as sent to supplier</Button
            >
            <Button
                v-if="order.status === 'ordered'"
                as-child
                class="bg-lime-600 hover:bg-lime-700"
            >
                <Link :href="receive({ query: { purchase_order: order.id } })"
                    ><PackageOpen /> Receive this delivery</Link
                >
            </Button>
            <Button variant="outline" @click="print"><Printer /> Print</Button>
            <Button
                v-if="order.status === 'draft' || order.status === 'ordered'"
                variant="outline"
                class="text-rose-600"
                @click="setStatus('cancelled')"
                ><XCircle /> Cancel order</Button
            >
        </div>

        <article
            class="max-w-3xl rounded-xl border bg-card p-8 print:border-0 print:p-0"
        >
            <header class="flex flex-wrap justify-between gap-4 border-b pb-5">
                <div>
                    <h1 class="text-2xl font-bold">Purchase order</h1>
                    <p class="text-muted-foreground">
                        {{ order.number }}
                        <span
                            class="ml-2 rounded-full bg-blue-100 px-2 py-0.5 text-sm font-medium text-blue-700 capitalize dark:bg-blue-500/20 dark:text-blue-300"
                            >{{ order.status }}</span
                        >
                    </p>
                </div>
                <div class="text-right text-sm">
                    <p class="font-semibold">
                        {{ order.branch.company_name ?? order.branch.name }}
                    </p>
                    <p>{{ order.branch.address }}</p>
                    <p>{{ order.branch.phone }}</p>
                </div>
            </header>
            <div class="grid gap-4 py-5 text-sm sm:grid-cols-3">
                <div>
                    <p class="text-muted-foreground">Supplier</p>
                    <p class="font-medium">{{ order.supplier.name }}</p>
                    <p>{{ order.supplier.phone }}</p>
                </div>
                <div>
                    <p class="text-muted-foreground">Date</p>
                    <p>{{ formatDate(order.created_at) }}</p>
                </div>
                <div>
                    <p class="text-muted-foreground">Deliver by</p>
                    <p>
                        {{
                            order.expected_on
                                ? formatDate(order.expected_on)
                                : 'As soon as possible'
                        }}
                    </p>
                </div>
            </div>
            <table class="w-full text-sm">
                <thead class="border-y text-left text-muted-foreground">
                    <tr>
                        <th class="py-2 font-medium">Product</th>
                        <th class="py-2 text-right font-medium">Qty</th>
                        <th class="py-2 text-right font-medium">Unit cost</th>
                        <th class="py-2 text-right font-medium">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="l in order.lines" :key="l.id" class="border-b">
                        <td class="py-2">
                            {{ l.product.name }} {{ l.product.strength }}
                        </td>
                        <td class="py-2 text-right">
                            {{ l.qty }} {{ l.product.unit }}
                        </td>
                        <td class="py-2 text-right">{{ rm(l.cost_sen) }}</td>
                        <td class="py-2 text-right">
                            {{ rm(l.qty * l.cost_sen) }}
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="py-3 text-right font-semibold">
                            Total
                        </td>
                        <td
                            class="py-3 text-right font-display text-lg font-bold"
                        >
                            {{ rm(total()) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
            <p v-if="order.note" class="mt-4 text-sm">
                <span class="text-muted-foreground">Note:</span>
                {{ order.note }}
            </p>
            <p class="mt-8 text-sm text-muted-foreground">
                Prepared by {{ order.user.name }}
            </p>
        </article>
    </div>
</template>
