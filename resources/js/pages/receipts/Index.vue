<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ClipboardList, Plus } from '@lucide/vue';
import Pagination from '@/components/Pagination.vue';
import PageHeader from '@/components/PageHeader.vue';
import SortableHead from '@/components/SortableHead.vue';
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
import { create, index } from '@/routes/receipts';
import type { Paginated, SortState } from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Goods received', href: index() }] },
});

type Receipt = {
    id: number;
    invoice_no: string | null;
    received_on: string;
    total_sen: number;
    payment_status: 'paid' | 'partial' | 'pending';
    lines_count: number;
    supplier: { id: number; name: string };
};

defineProps<{
    sort: SortState;
    receipts: Paginated<Receipt>;
}>();

const statusTone = {
    paid: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300',
    partial:
        'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
    pending: 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
};
</script>

<template>
    <Head title="Goods received" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <PageHeader
            :title="$t('Goods received')"
            :description="
                $t('Each delivery adds batches with their expiry dates.')
            "
            :icon="ClipboardList"
            tone="bg-lime-100 text-lime-700 dark:bg-lime-500/20 dark:text-lime-300"
        >
            <Button as-child class="bg-lime-600 hover:bg-lime-700">
                <Link :href="create()"><Plus /> {{ $t('Receive stock') }}</Link>
            </Button>
        </PageHeader>

        <div class="rounded-xl border bg-card">
            <Table>
                <TableHeader>
                    <TableRow>
                        <SortableHead name="received_on" :sort="sort">{{
                            $t('Received')
                        }}</SortableHead>
                        <SortableHead name="supplier" :sort="sort">{{
                            $t('Supplier')
                        }}</SortableHead>
                        <SortableHead name="invoice_no" :sort="sort">{{
                            $t('Invoice')
                        }}</SortableHead>
                        <TableHead class="text-right">{{
                            $t('Lines')
                        }}</TableHead>
                        <SortableHead
                            name="total_sen"
                            :sort="sort"
                            align="right"
                            >{{ $t('Total') }}</SortableHead
                        >
                        <SortableHead name="payment_status" :sort="sort">{{
                            $t('Payment')
                        }}</SortableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!receipts.data.length">
                        <TableCell
                            colspan="6"
                            class="py-10 text-center text-muted-foreground"
                            >{{ $t('No deliveries recorded yet.') }}</TableCell
                        >
                    </TableRow>
                    <TableRow v-for="r in receipts.data" :key="r.id">
                        <TableCell>{{ formatDate(r.received_on) }}</TableCell>
                        <TableCell class="font-medium">{{
                            r.supplier.name
                        }}</TableCell>
                        <TableCell>{{ r.invoice_no }}</TableCell>
                        <TableCell class="text-right">{{
                            r.lines_count
                        }}</TableCell>
                        <TableCell class="text-right">{{
                            rm(r.total_sen)
                        }}</TableCell>
                        <TableCell>
                            <span
                                :class="[
                                    'rounded-full px-2 py-0.5 text-sm font-medium capitalize',
                                    statusTone[r.payment_status],
                                ]"
                                >{{ $t(r.payment_status) }}</span
                            >
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
        <Pagination :page="receipts" />
    </div>
</template>
