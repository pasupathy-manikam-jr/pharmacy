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
import { formatDateTime, rm } from '@/lib/money';
import { index } from '@/routes/audit';
import type { Paginated, SortState } from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Audit log', href: index() }] },
});

type Log = {
    id: number;
    created_at: string;
    action: string;
    subject_type: string | null;
    subject_id: number | null;
    data: Record<string, unknown> | null;
    ip: string | null;
    user: { name: string } | null;
};

const props = defineProps<{
    sort: SortState;
    logs: Paginated<Log>;
    action: string;
    actions: string[];
}>();

const a = ref(props.action || 'all');
watch(a, (v) =>
    router.get(
        index().url,
        { action: v === 'all' ? undefined : v },
        { preserveState: true },
    ),
);

const area = (action: string) => action.split('.')[0] ?? action;
const tones: Record<string, string> = {
    sale: 'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300',
    stock: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
    shift: 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-300',
    user: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-500/20 dark:text-cyan-300',
    einvoice: 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
    product:
        'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300',
    products:
        'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300',
    customer:
        'bg-pink-100 text-pink-700 dark:bg-pink-500/20 dark:text-pink-300',
    branch: 'bg-teal-100 text-teal-700 dark:bg-teal-500/20 dark:text-teal-300',
    purchase_order:
        'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300',
};
const describe = (data: Record<string, unknown> | null) =>
    data
        ? Object.entries(data)
              .filter(([, v]) => v !== null && typeof v !== 'object')
              .map(
                  ([k, v]) =>
                      `${k.replace(/_sen$/, '').replace(/_/g, ' ')}: ${k.endsWith('_sen') ? rm(Number(v)) : String(v)}`,
              )
              .join(', ')
        : '';
</script>

<template>
    <Head title="Audit log" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <PageHeader
            :title="$t('Audit log')"
            :description="
                $t('Who changed what. Entries can’t be edited or removed.')
            "
            :icon="History"
            tone="bg-slate-200 text-slate-700 dark:bg-slate-500/20 dark:text-slate-300"
        >
            <Select v-model="a">
                <SelectTrigger class="w-56"><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">{{
                        $t('All activity')
                    }}</SelectItem>
                    <SelectItem v-for="x in actions" :key="x" :value="x">{{
                        x
                    }}</SelectItem>
                </SelectContent>
            </Select>
        </PageHeader>

        <div class="rounded-xl border bg-card">
            <Table>
                <TableHeader>
                    <TableRow>
                        <SortableHead name="created_at" :sort="sort">{{
                            $t('When')
                        }}</SortableHead>
                        <SortableHead name="user" :sort="sort">{{
                            $t('Who')
                        }}</SortableHead>
                        <SortableHead name="action" :sort="sort">{{
                            $t('What')
                        }}</SortableHead>
                        <TableHead>{{ $t('Details') }}</TableHead>
                        <TableHead>{{ $t('IP') }}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!logs.data.length">
                        <TableCell
                            colspan="5"
                            class="py-10 text-center text-muted-foreground"
                            >{{ $t('No activity recorded yet.') }}</TableCell
                        >
                    </TableRow>
                    <TableRow v-for="l in logs.data" :key="l.id">
                        <TableCell class="whitespace-nowrap">{{
                            formatDateTime(l.created_at)
                        }}</TableCell>
                        <TableCell>{{
                            l.user?.name ?? $t('System')
                        }}</TableCell>
                        <TableCell
                            ><span
                                :class="[
                                    'rounded-full px-2 py-0.5 text-sm font-medium',
                                    tones[area(l.action)] ?? 'bg-muted',
                                ]"
                                >{{ l.action }}</span
                            ></TableCell
                        >
                        <TableCell
                            class="max-w-md text-sm text-muted-foreground"
                            >{{ describe(l.data) }}</TableCell
                        >
                        <TableCell class="text-sm text-muted-foreground">{{
                            l.ip
                        }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
        <Pagination :page="logs" />
    </div>
</template>
