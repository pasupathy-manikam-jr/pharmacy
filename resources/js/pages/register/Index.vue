<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { BookLock, Printer } from '@lucide/vue';
import { reactive, watch } from 'vue';
import DatePicker from '@/components/DatePicker.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/money';
import { index } from '@/routes/register';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Poison registers', href: index() }] },
});

type Entry = {
    id: number;
    created_at: string;
    qty: number;
    balance_after: number;
    customer_name: string;
    customer_ic: string | null;
    customer_address: string | null;
    prescriber: string | null;
    dosage: string | null;
    reverses_id: number | null;
    product: { name: string; strength: string | null; unit: string };
    batch: { batch_no: string };
    pharmacist: { name: string };
};

const props = defineProps<{
    entries: Entry[];
    filters: { register: string; from: string; to: string };
    registers: string[];
}>();

const labels: Record<string, string> = {
    prescription_book: 'Prescription Book',
    poisons_book: 'Poisons Book',
    psychotropic: 'Psychotropic',
    dda: 'Dangerous Drugs',
};

const print = () => window.print();
const f = reactive({ ...props.filters });
watch(f, () =>
    router.get(index().url, { ...f }, { preserveState: true, replace: true }),
);
</script>

<template>
    <Head :title="labels[filters.register]" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <PageHeader
            :title="labels[filters.register]"
            description="Written automatically at the counter. Entries are never edited; refunds add a reversing line."
            :icon="BookLock"
            tone="bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300"
        >
            <Button variant="outline" class="no-print" @click="print"
                ><Printer /> Print</Button
            >
        </PageHeader>

        <div class="no-print flex flex-wrap items-end gap-3">
            <div class="flex rounded-lg border bg-card p-1">
                <button
                    v-for="r in registers"
                    :key="r"
                    type="button"
                    :class="[
                        'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                        f.register === r
                            ? 'bg-rose-600 text-white'
                            : 'text-muted-foreground hover:text-foreground',
                    ]"
                    @click="f.register = r"
                >
                    {{ labels[r] }}
                </button>
            </div>
            <div class="w-44"><DatePicker v-model="f.from" /></div>
            <span class="pb-2 text-muted-foreground">to</span>
            <div class="w-44"><DatePicker v-model="f.to" /></div>
        </div>

        <div class="rounded-xl border bg-card">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>No.</TableHead>
                        <TableHead>Date</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead>Product · batch</TableHead>
                        <TableHead class="text-right">Qty</TableHead>
                        <TableHead class="text-right">Balance</TableHead>
                        <TableHead>Prescriber</TableHead>
                        <TableHead>Directions</TableHead>
                        <TableHead>Pharmacist</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!entries.length">
                        <TableCell
                            colspan="9"
                            class="py-10 text-center text-muted-foreground"
                            >No entries in this period.</TableCell
                        >
                    </TableRow>
                    <TableRow
                        v-for="e in entries"
                        :key="e.id"
                        :class="
                            e.reverses_id
                                ? 'bg-rose-50/60 dark:bg-rose-500/5'
                                : ''
                        "
                    >
                        <TableCell class="text-muted-foreground">{{
                            e.id
                        }}</TableCell>
                        <TableCell class="whitespace-nowrap">{{
                            formatDateTime(e.created_at)
                        }}</TableCell>
                        <TableCell>
                            <p class="font-medium">{{ e.customer_name }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ e.customer_ic }} {{ e.customer_address }}
                            </p>
                        </TableCell>
                        <TableCell>
                            {{ e.product.name }} {{ e.product.strength }}
                            <p class="text-sm text-muted-foreground">
                                {{ e.batch.batch_no }}
                            </p>
                        </TableCell>
                        <TableCell
                            :class="[
                                'text-right font-semibold',
                                e.qty < 0 && 'text-rose-600',
                            ]"
                        >
                            {{ e.qty }}
                            <p v-if="e.reverses_id" class="text-xs font-normal">
                                reverses #{{ e.reverses_id }}
                            </p>
                        </TableCell>
                        <TableCell class="text-right">{{
                            e.balance_after
                        }}</TableCell>
                        <TableCell>{{ e.prescriber }}</TableCell>
                        <TableCell>{{ e.dosage }}</TableCell>
                        <TableCell>{{ e.pharmacist.name }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
