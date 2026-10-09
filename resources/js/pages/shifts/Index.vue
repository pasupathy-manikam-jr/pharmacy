<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Wallet } from '@lucide/vue';
import { computed } from 'vue';
import ShiftController from '@/actions/App/Http/Controllers/Pharmacy/ShiftController';
import InputError from '@/components/InputError.vue';
import OpenShiftForm from '@/components/OpenShiftForm.vue';
import PageHeader from '@/components/PageHeader.vue';
import SortableHead from '@/components/SortableHead.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime, rm, toSen } from '@/lib/money';
import { index } from '@/routes/shifts';
import type { Paginated, SortState } from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'My shift', href: index() }] },
});

type Summary = {
    float: number;
    sales: number;
    refunds: number;
    payments: number;
    expected: number;
};
type ShiftRow = {
    id: number;
    opened_at: string;
    closed_at: string | null;
    opening_float_sen: number;
    expected_cash_sen: number | null;
    counted_cash_sen: number | null;
    note: string | null;
    user?: { name: string };
};

const props = defineProps<{
    sort: SortState;
    current: (ShiftRow & { summary: Summary }) | null;
    history: Paginated<ShiftRow>;
}>();

const form = useForm({ counted: '', note: '' });
const variance = computed(() =>
    props.current && form.counted !== ''
        ? toSen(form.counted) - props.current.summary.expected
        : null,
);
const close = () =>
    form
        .transform((d) => ({
            counted_cash_sen: toSen(d.counted),
            note: d.note || null,
        }))
        .post(ShiftController.close.url(), { onSuccess: () => form.reset() });

const varianceTone = (v: number) =>
    v === 0 ? 'text-emerald-600' : v > 0 ? 'text-amber-600' : 'text-rose-600';
</script>

<template>
    <Head title="My shift" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <PageHeader
            :title="$t('My shift')"
            :description="
                $t(
                    'Open the drawer with a float, close it by counting the cash.',
                )
            "
            :icon="Wallet"
            tone="bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-300"
        />

        <section
            v-if="!current"
            class="rounded-xl border border-green-200 bg-green-50/60 p-5 dark:border-green-500/30 dark:bg-green-500/5"
        >
            <p class="mb-4 text-muted-foreground">
                {{
                    $t(
                        'You have no open shift. Count the float in the drawer, then open your shift to start selling.',
                    )
                }}
            </p>
            <OpenShiftForm />
        </section>

        <section v-else class="grid gap-5 lg:grid-cols-2">
            <div class="rounded-xl border bg-card p-5">
                <p class="text-sm text-muted-foreground">
                    {{
                        $t('Open since :when', {
                            when: formatDateTime(current.opened_at),
                        })
                    }}
                </p>
                <dl class="tabular mt-4 grid grid-cols-2 gap-y-2">
                    <dt class="text-muted-foreground">
                        {{ $t('Opening float') }}
                    </dt>
                    <dd class="text-right">{{ rm(current.summary.float) }}</dd>
                    <dt class="text-muted-foreground">
                        {{ $t('Cash sales') }}
                    </dt>
                    <dd
                        class="text-right text-emerald-700 dark:text-emerald-300"
                    >
                        + {{ rm(current.summary.sales) }}
                    </dd>
                    <dt class="text-muted-foreground">
                        {{ $t('Account payments in cash') }}
                    </dt>
                    <dd
                        class="text-right text-emerald-700 dark:text-emerald-300"
                    >
                        + {{ rm(current.summary.payments) }}
                    </dd>
                    <dt class="text-muted-foreground">
                        {{ $t('Cash refunds') }}
                    </dt>
                    <dd class="text-right text-rose-600">
                        − {{ rm(current.summary.refunds) }}
                    </dd>
                    <dt class="border-t pt-2 font-semibold">
                        {{ $t('Expected in drawer') }}
                    </dt>
                    <dd
                        class="border-t pt-2 text-right font-display text-2xl font-bold"
                    >
                        {{ rm(current.summary.expected) }}
                    </dd>
                </dl>
            </div>
            <form
                class="flex flex-col gap-4 rounded-xl border border-green-200 bg-green-50/60 p-5 dark:border-green-500/30 dark:bg-green-500/5"
                novalidate
                @submit.prevent="close"
            >
                <h2 class="font-semibold">{{ $t('Close shift') }}</h2>
                <div class="grid gap-1.5">
                    <Label for="counted">{{
                        $t('Cash counted in drawer (RM)')
                    }}</Label>
                    <Input
                        id="counted"
                        v-model="form.counted"
                        inputmode="decimal"
                        placeholder="0.00"
                    />
                    <InputError
                        :message="
                            (form.errors as Record<string, string>)
                                .counted_cash_sen
                        "
                    />
                </div>
                <p
                    v-if="variance !== null"
                    :class="['font-medium', varianceTone(variance)]"
                >
                    {{
                        variance === 0
                            ? $t('Drawer balances exactly.')
                            : variance > 0
                              ? $t('Over by :amount', { amount: rm(variance) })
                              : $t('Short by :amount', {
                                    amount: rm(-variance),
                                })
                    }}
                </p>
                <div class="grid gap-1.5">
                    <Label for="note">{{ $t('Note') }}</Label>
                    <Input
                        id="note"
                        v-model="form.note"
                        :placeholder="$t('Explain any difference')"
                    />
                </div>
                <Button
                    :disabled="form.processing"
                    class="self-start bg-green-600 hover:bg-green-700"
                    >{{ $t('Close shift') }}</Button
                >
            </form>
        </section>

        <div class="rounded-xl border bg-card">
            <Table>
                <TableHeader>
                    <TableRow>
                        <SortableHead name="opened_at" :sort="sort">{{
                            $t('Opened')
                        }}</SortableHead>
                        <SortableHead name="closed_at" :sort="sort">{{
                            $t('Closed')
                        }}</SortableHead>
                        <SortableHead name="staff" :sort="sort">{{
                            $t('Staff')
                        }}</SortableHead>
                        <SortableHead
                            name="expected"
                            :sort="sort"
                            align="right"
                            >{{ $t('Expected') }}</SortableHead
                        >
                        <SortableHead
                            name="counted"
                            :sort="sort"
                            align="right"
                            >{{ $t('Counted') }}</SortableHead
                        >
                        <SortableHead
                            name="variance"
                            :sort="sort"
                            align="right"
                            >{{ $t('Difference') }}</SortableHead
                        >
                        <TableHead>{{ $t('Note') }}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!history.data.length">
                        <TableCell
                            colspan="7"
                            class="py-10 text-center text-muted-foreground"
                            >{{ $t('No closed shifts yet.') }}</TableCell
                        >
                    </TableRow>
                    <TableRow v-for="s in history.data" :key="s.id">
                        <TableCell>{{ formatDateTime(s.opened_at) }}</TableCell>
                        <TableCell>{{
                            s.closed_at ? formatDateTime(s.closed_at) : ''
                        }}</TableCell>
                        <TableCell>{{ s.user?.name }}</TableCell>
                        <TableCell class="text-right">{{
                            rm(s.expected_cash_sen ?? 0)
                        }}</TableCell>
                        <TableCell class="text-right">{{
                            rm(s.counted_cash_sen ?? 0)
                        }}</TableCell>
                        <TableCell
                            :class="[
                                'text-right font-semibold',
                                varianceTone(
                                    (s.counted_cash_sen ?? 0) -
                                        (s.expected_cash_sen ?? 0),
                                ),
                            ]"
                        >
                            {{
                                rm(
                                    (s.counted_cash_sen ?? 0) -
                                        (s.expected_cash_sen ?? 0),
                                )
                            }}
                        </TableCell>
                        <TableCell class="text-muted-foreground">{{
                            s.note
                        }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
        <Pagination :page="history" />
    </div>
</template>
