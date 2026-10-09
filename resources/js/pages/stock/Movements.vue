<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { History } from '@lucide/vue';
import { ref, watch } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import SortableHead from '@/components/SortableHead.vue';
import Pagination from '@/components/Pagination.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/money';
import { index as stock, movements as movementsRoute } from '@/routes/stock';
import type { Paginated, SortState } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Stock', href: stock() },
            { title: 'Movements', href: movementsRoute() },
        ],
    },
});

type Movement = {
    id: number;
    created_at: string;
    type: string;
    reason: string | null;
    qty_delta: number;
    qty_after: number;
    note: string | null;
    batch: {
        batch_no: string;
        product: { name: string; strength: string | null };
    };
};

const props = defineProps<{
    sort: SortState;
    movements: Paginated<Movement>;
    type: string;
}>();

const types: Record<string, { label: string; tone: string }> = {
    receipt: {
        label: 'Received',
        tone: 'bg-lime-100 text-lime-800 dark:bg-lime-500/20 dark:text-lime-300',
    },
    sale: {
        label: 'Sold',
        tone: 'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300',
    },
    refund: {
        label: 'Refunded',
        tone: 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
    },
    adjustment: {
        label: 'Adjusted',
        tone: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
    },
    transfer_out: {
        label: 'Sent to branch',
        tone: 'bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-300',
    },
    transfer_in: {
        label: 'From branch',
        tone: 'bg-teal-100 text-teal-700 dark:bg-teal-500/20 dark:text-teal-300',
    },
};

const t = ref(props.type || 'all');
watch(t, (v) =>
    router.get(
        movementsRoute().url,
        { type: v === 'all' ? undefined : v },
        { preserveState: true },
    ),
);
</script>

<template>
    <Head title="Stock movements" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <PageHeader
            title="Stock movements"
            description="Every change to stock, newest first. Nothing here can be edited."
            :icon="History"
            tone="bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300"
        >
            <Select v-model="t">
                <SelectTrigger class="w-48"><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All movements</SelectItem>
                    <SelectItem v-for="(v, k) in types" :key="k" :value="k">{{
                        v.label
                    }}</SelectItem>
                </SelectContent>
            </Select>
        </PageHeader>

        <div class="rounded-xl border bg-card">
            <Table>
                <TableHeader>
                    <TableRow>
                        <SortableHead name="created_at" :sort="sort"
                            >When</SortableHead
                        >
                        <SortableHead name="product" :sort="sort"
                            >Product</SortableHead
                        >
                        <SortableHead name="batch_no" :sort="sort"
                            >Batch</SortableHead
                        >
                        <SortableHead name="type" :sort="sort"
                            >What</SortableHead
                        >
                        <SortableHead
                            name="qty_delta"
                            :sort="sort"
                            align="right"
                            >Change</SortableHead
                        >
                        <SortableHead
                            name="qty_after"
                            :sort="sort"
                            align="right"
                            >Left</SortableHead
                        >
                        <TableHead>Note</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!movements.data.length">
                        <TableCell
                            colspan="7"
                            class="py-10 text-center text-muted-foreground"
                            >No movements.</TableCell
                        >
                    </TableRow>
                    <TableRow v-for="m in movements.data" :key="m.id">
                        <TableCell class="whitespace-nowrap">{{
                            formatDateTime(m.created_at)
                        }}</TableCell>
                        <TableCell class="font-medium"
                            >{{ m.batch.product.name }}
                            {{ m.batch.product.strength }}</TableCell
                        >
                        <TableCell class="text-muted-foreground">{{
                            m.batch.batch_no
                        }}</TableCell>
                        <TableCell>
                            <span
                                :class="[
                                    'rounded-full px-2 py-0.5 text-sm font-medium',
                                    types[m.type]?.tone,
                                ]"
                                >{{ types[m.type]?.label ?? m.type }}</span
                            >
                            <span
                                v-if="m.reason"
                                class="ml-1 text-sm text-muted-foreground"
                                >{{ m.reason.replace('_', ' ') }}</span
                            >
                        </TableCell>
                        <TableCell
                            :class="[
                                'text-right font-semibold',
                                m.qty_delta < 0
                                    ? 'text-rose-600'
                                    : 'text-emerald-600',
                            ]"
                            >{{ m.qty_delta > 0 ? '+' : ''
                            }}{{ m.qty_delta }}</TableCell
                        >
                        <TableCell class="text-right">{{
                            m.qty_after
                        }}</TableCell>
                        <TableCell class="text-muted-foreground">{{
                            m.note
                        }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
        <Pagination :page="movements" />
    </div>
</template>
