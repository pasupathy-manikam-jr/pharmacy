<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus, ShoppingCart } from '@lucide/vue';
import PageHeader from '@/components/PageHeader.vue';
import SortableHead from '@/components/SortableHead.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate, rm } from '@/lib/money';
import { create, index, show } from '@/routes/purchase-orders';
import type { Paginated, SortState } from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Purchase orders', href: index() }] },
});

type Order = {
    id: number;
    number: string;
    status: 'draft' | 'ordered' | 'received' | 'cancelled';
    created_at: string;
    expected_on: string | null;
    lines_count: number;
    total_sen: number;
    supplier: { name: string };
};

defineProps<{
    sort: SortState;
    orders: Paginated<Order>;
}>();

const tone = {
    draft: 'bg-slate-100 text-slate-700 dark:bg-slate-500/20 dark:text-slate-300',
    ordered: 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300',
    received:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300',
    cancelled:
        'bg-neutral-200 text-neutral-600 dark:bg-neutral-500/20 dark:text-neutral-400',
};
</script>

<template>
    <Head title="Purchase orders" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <PageHeader
            title="Purchase orders"
            description="What you’ve asked suppliers for. Receive a delivery against its order."
            :icon="ShoppingCart"
            tone="bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300"
        >
            <Button as-child class="bg-blue-600 hover:bg-blue-700"
                ><Link :href="create()"><Plus /> New order</Link></Button
            >
        </PageHeader>

        <div class="rounded-xl border bg-card">
            <Table>
                <TableHeader>
                    <TableRow>
                        <SortableHead name="number" :sort="sort"
                            >Order</SortableHead
                        >
                        <SortableHead name="supplier" :sort="sort"
                            >Supplier</SortableHead
                        >
                        <SortableHead name="created_at" :sort="sort"
                            >Created</SortableHead
                        >
                        <SortableHead name="expected_on" :sort="sort"
                            >Expected</SortableHead
                        >
                        <TableHead class="text-right">Lines</TableHead>
                        <SortableHead
                            name="total_sen"
                            :sort="sort"
                            align="right"
                            >Total</SortableHead
                        >
                        <SortableHead name="status" :sort="sort"
                            >Status</SortableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!orders.data.length">
                        <TableCell
                            colspan="7"
                            class="py-10 text-center text-muted-foreground"
                            >No orders yet. Start one from what’s running
                            low.</TableCell
                        >
                    </TableRow>
                    <TableRow v-for="o in orders.data" :key="o.id">
                        <TableCell
                            ><Link
                                :href="show(o.id)"
                                class="font-medium text-blue-700 hover:underline dark:text-blue-300"
                                >{{ o.number }}</Link
                            ></TableCell
                        >
                        <TableCell>{{ o.supplier.name }}</TableCell>
                        <TableCell>{{ formatDate(o.created_at) }}</TableCell>
                        <TableCell>{{
                            o.expected_on ? formatDate(o.expected_on) : ''
                        }}</TableCell>
                        <TableCell class="text-right">{{
                            o.lines_count
                        }}</TableCell>
                        <TableCell class="text-right">{{
                            rm(Number(o.total_sen))
                        }}</TableCell>
                        <TableCell
                            ><span
                                :class="[
                                    'rounded-full px-2 py-0.5 text-sm font-medium capitalize',
                                    tone[o.status],
                                ]"
                                >{{ o.status }}</span
                            ></TableCell
                        >
                    </TableRow>
                </TableBody>
            </Table>
        </div>
        <Pagination :page="orders" />
    </div>
</template>
