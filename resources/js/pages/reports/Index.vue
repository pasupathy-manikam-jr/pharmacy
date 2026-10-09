<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { BarChart3, Download } from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import DatePicker from '@/components/DatePicker.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate, rm } from '@/lib/money';
import { index } from '@/routes/reports';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Reports', href: index() }] },
});

type Row = Record<string, string | number | null>;

const props = defineProps<{
    rows: Row[];
    filters: { report: string; from: string; to: string };
    reports: Record<string, string>;
}>();

const f = reactive({ ...props.filters });
watch(f, () =>
    router.get(index().url, { ...f }, { preserveState: true, replace: true }),
);

const columns = computed(() =>
    props.rows[0] ? Object.keys(props.rows[0]) : [],
);
const isMoney = (c: string) => c.endsWith('_sen');
const isNumber = (c: string) =>
    isMoney(c) || ['qty', 'receipts', 'margin_pct'].includes(c);
const heading = (c: string) =>
    ({ margin_pct: 'Margin %', qty: 'Qty' })[c] ??
    c
        .replace(/_sen$/, '')
        .replace(/_/g, ' ')
        .replace(/^./, (x) => x.toUpperCase());
const cell = (c: string, v: string | number | null) =>
    v === null
        ? ''
        : isMoney(c)
          ? rm(Number(v))
          : c === 'day'
            ? formatDate(String(v))
            : c === 'method'
              ? ((
                    {
                        cash: 'Cash',
                        card: 'Card',
                        ewallet: 'E-wallet',
                        credit: 'On account',
                    } as Record<string, string>
                )[String(v)] ?? v)
              : v;
const totals = computed(() =>
    Object.fromEntries(
        columns.value
            .filter((c) => isMoney(c) || ['qty', 'receipts'].includes(c))
            .map((c) => [
                c,
                props.rows.reduce((s, r) => s + Number(r[c] ?? 0), 0),
            ]),
    ),
);
const csvUrl = computed(
    () =>
        `${index().url}?${new URLSearchParams({ ...f, export: 'csv' }).toString()}`,
);
const tabs = [
    'bg-yellow-500',
    'bg-violet-600',
    'bg-sky-600',
    'bg-emerald-600',
    'bg-amber-600',
    'bg-rose-600',
];
</script>

<template>
    <Head title="Reports" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <PageHeader
            title="Reports"
            description="Net of refunds. Costs come from the batch each unit was sold from."
            :icon="BarChart3"
            tone="bg-yellow-100 text-yellow-700 dark:bg-yellow-500/20 dark:text-yellow-300"
        >
            <Button variant="outline" as-child
                ><a :href="csvUrl"><Download /> Download CSV</a></Button
            >
        </PageHeader>

        <div class="flex flex-wrap items-center gap-3">
            <div class="flex flex-wrap rounded-lg border bg-card p-1">
                <button
                    v-for="(label, key, i) in reports"
                    :key="key"
                    type="button"
                    :class="[
                        'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                        f.report === key
                            ? `${tabs[i]} text-white`
                            : 'text-muted-foreground hover:text-foreground',
                    ]"
                    @click="f.report = String(key)"
                >
                    {{ label }}
                </button>
            </div>
            <template v-if="f.report !== 'valuation'">
                <div class="w-44"><DatePicker v-model="f.from" /></div>
                <span class="text-muted-foreground">to</span>
                <div class="w-44"><DatePicker v-model="f.to" /></div>
            </template>
        </div>

        <div class="rounded-xl border bg-card">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead
                            v-for="c in columns"
                            :key="c"
                            :class="isNumber(c) && 'text-right'"
                            >{{ heading(c) }}</TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!rows.length">
                        <TableCell
                            class="py-10 text-center text-muted-foreground"
                            >Nothing in this period.</TableCell
                        >
                    </TableRow>
                    <TableRow v-for="(r, i) in rows" :key="i">
                        <TableCell
                            v-for="c in columns"
                            :key="c"
                            :class="[
                                isNumber(c) && 'text-right',
                                c === 'margin_sen' &&
                                    Number(r[c]) < 0 &&
                                    'text-rose-600',
                            ]"
                            >{{ cell(c, r[c]) }}</TableCell
                        >
                    </TableRow>
                </TableBody>
                <TableFooter
                    v-if="rows.length > 1 && Object.keys(totals).length"
                >
                    <TableRow>
                        <TableCell
                            v-for="(c, i) in columns"
                            :key="c"
                            :class="[
                                'font-semibold',
                                isNumber(c) && 'text-right',
                            ]"
                        >
                            {{
                                i === 0
                                    ? 'Total'
                                    : c in totals
                                      ? isMoney(c)
                                          ? rm(totals[c] ?? 0)
                                          : totals[c]
                                      : ''
                            }}
                        </TableCell>
                    </TableRow>
                </TableFooter>
            </Table>
        </div>
    </div>
</template>
