<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FileText, Search } from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import SortableHead from '@/components/SortableHead.vue';
import Pagination from '@/components/Pagination.vue';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/money';
import { show as customerShow } from '@/routes/customers';
import { index } from '@/routes/prescriptions';
import type { Paginated, SortState } from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Prescriptions', href: index() }] },
});

type Row = {
    id: number;
    prescriber_name: string;
    prescriber_reg_no: string | null;
    clinic: string | null;
    diagnosis: string | null;
    issued_on: string;
    refills_allowed: number;
    sales_count: number;
    customer: { id: number; name: string; ic_no: string | null };
};

const props = defineProps<{
    sort: SortState;
    prescriptions: Paginated<Row>;
    search: string;
}>();
const q = ref(props.search);
const submit = () =>
    router.get(
        index().url,
        { search: q.value || undefined },
        { preserveState: true },
    );
</script>

<template>
    <Head title="Prescriptions" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <PageHeader
            :title="$t('Prescriptions')"
            :description="
                $t(
                    'Captured at the counter. Refills are dispensed from the POS.',
                )
            "
            :icon="FileText"
            tone="bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-500/20 dark:text-fuchsia-300"
        >
            <form class="relative" novalidate @submit.prevent="submit">
                <Search
                    class="absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                />
                <Input
                    v-model="q"
                    class="w-64 pl-8"
                    :placeholder="$t('Patient, MyKad or prescriber')"
                />
            </form>
        </PageHeader>

        <div class="rounded-xl border bg-card">
            <Table>
                <TableHeader>
                    <TableRow>
                        <SortableHead name="issued_on" :sort="sort">{{
                            $t('Issued')
                        }}</SortableHead>
                        <SortableHead name="patient" :sort="sort">{{
                            $t('Patient')
                        }}</SortableHead>
                        <SortableHead name="prescriber" :sort="sort">{{
                            $t('Prescriber')
                        }}</SortableHead>
                        <SortableHead name="diagnosis" :sort="sort">{{
                            $t('Diagnosis')
                        }}</SortableHead>
                        <TableHead>{{ $t('Dispensed') }}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!prescriptions.data.length">
                        <TableCell
                            colspan="5"
                            class="py-10 text-center text-muted-foreground"
                            >{{ $t('No prescriptions found.') }}</TableCell
                        >
                    </TableRow>
                    <TableRow v-for="p in prescriptions.data" :key="p.id">
                        <TableCell>{{ formatDate(p.issued_on) }}</TableCell>
                        <TableCell>
                            <Link
                                :href="customerShow(p.customer.id)"
                                class="font-medium text-pink-700 hover:underline dark:text-pink-300"
                                >{{ p.customer.name }}</Link
                            >
                            <p class="text-sm text-muted-foreground">
                                {{ p.customer.ic_no }}
                            </p>
                        </TableCell>
                        <TableCell
                            >{{ p.prescriber_name }}
                            <span class="text-muted-foreground">{{
                                p.prescriber_reg_no
                            }}</span>
                            <p class="text-sm text-muted-foreground">
                                {{ p.clinic }}
                            </p></TableCell
                        >
                        <TableCell>{{ p.diagnosis }}</TableCell>
                        <TableCell>
                            <span
                                :class="[
                                    'rounded-full px-2 py-0.5 text-sm font-medium',
                                    p.sales_count >= 1 + p.refills_allowed
                                        ? 'bg-neutral-200 text-neutral-700 dark:bg-neutral-500/20 dark:text-neutral-300'
                                        : 'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-500/20 dark:text-fuchsia-300',
                                ]"
                            >
                                {{
                                    $t(':done of :total', {
                                        done: p.sales_count,
                                        total: 1 + p.refills_allowed,
                                    })
                                }}
                            </span>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
        <Pagination :page="prescriptions" />
    </div>
</template>
