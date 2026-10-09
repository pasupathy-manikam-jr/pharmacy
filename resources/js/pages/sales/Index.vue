<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Input } from '@/components/ui/input';
import { ReceiptText, Search } from '@lucide/vue';
import Pagination from '@/components/Pagination.vue';
import PageHeader from '@/components/PageHeader.vue';
import SortableHead from '@/components/SortableHead.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime, rm } from '@/lib/money';
import { index, show } from '@/routes/sales';
import type { Paginated, SortState } from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Sales', href: index() }] },
});

type SaleRow = {
    id: number;
    number: string;
    created_at: string;
    total_sen: number;
    payment_method: string;
    status: 'completed' | 'partially_refunded' | 'refunded';
    customer: { name: string } | null;
    user: { name: string };
};

const props = defineProps<{
    sort: SortState;
    sales: Paginated<SaleRow>;
    search: string;
}>();

const q = ref(props.search);
const submit = () =>
    router.get(
        index().url,
        { search: q.value || undefined },
        { preserveState: true },
    );

const methodTone: Record<string, string> = {
    cash: 'text-emerald-700 dark:text-emerald-300',
    card: 'text-sky-700 dark:text-sky-300',
    ewallet: 'text-fuchsia-700 dark:text-fuchsia-300',
    credit: 'text-amber-700 dark:text-amber-300',
};
const methodLabel: Record<string, string> = {
    cash: 'Cash',
    card: 'Card',
    ewallet: 'E-wallet',
    credit: 'On account',
};
</script>

<template>
    <Head title="Sales" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <PageHeader
            title="Sales"
            description="Every receipt from this branch. Open one to reprint or refund."
            :icon="ReceiptText"
            tone="bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300"
        >
            <form class="relative" novalidate @submit.prevent="submit">
                <Search
                    class="absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                />
                <Input
                    v-model="q"
                    class="w-56 pl-8"
                    placeholder="Receipt number"
                />
            </form>
        </PageHeader>

        <div class="rounded-xl border bg-card">
            <Table>
                <TableHeader>
                    <TableRow>
                        <SortableHead name="number" :sort="sort"
                            >Receipt</SortableHead
                        >
                        <SortableHead name="created_at" :sort="sort"
                            >When</SortableHead
                        >
                        <SortableHead name="customer" :sort="sort"
                            >Customer</SortableHead
                        >
                        <SortableHead name="cashier" :sort="sort"
                            >Cashier</SortableHead
                        >
                        <SortableHead name="payment_method" :sort="sort"
                            >Paid by</SortableHead
                        >
                        <SortableHead
                            name="total_sen"
                            :sort="sort"
                            align="right"
                            >Total</SortableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!sales.data.length">
                        <TableCell
                            colspan="6"
                            class="py-10 text-center text-muted-foreground"
                            >No sales yet today.</TableCell
                        >
                    </TableRow>
                    <TableRow
                        v-for="s in sales.data"
                        :key="s.id"
                        :class="
                            s.status === 'refunded' &&
                            'text-muted-foreground line-through'
                        "
                    >
                        <TableCell>
                            <Link
                                :href="show(s.id)"
                                class="font-medium text-sky-700 hover:underline dark:text-sky-300"
                                >{{ s.number }}</Link
                            >
                        </TableCell>
                        <TableCell>{{
                            formatDateTime(s.created_at)
                        }}</TableCell>
                        <TableCell>{{
                            s.customer?.name ?? 'Walk-in'
                        }}</TableCell>
                        <TableCell>{{ s.user.name }}</TableCell>
                        <TableCell
                            :class="[
                                'font-medium',
                                methodTone[s.payment_method],
                            ]"
                            >{{ methodLabel[s.payment_method] }}</TableCell
                        >
                        <TableCell class="text-right font-semibold">{{
                            rm(s.total_sen)
                        }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
        <Pagination :page="sales" />
    </div>
</template>
