<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarClock, PackageOpen, ScanBarcode } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { formatDate, rm } from '@/lib/money';
import { dashboard } from '@/routes';
import { index as pos } from '@/routes/pos';
import { create as newReceipt } from '@/routes/receipts';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Dashboard', href: dashboard() }] },
});

defineProps<{
    stats: {
        sales_count: number;
        sales_total_sen: number;
        near_expiry: number;
        low_stock: number;
    };
    nearExpiry: {
        product: string;
        batch_no: string;
        expiry_date: string;
        expired: boolean;
        qty: number;
    }[];
    lowStock: {
        id: number;
        name: string;
        reorder_level: number;
        on_hand: number;
    }[];
}>();
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <!-- Today's takings lead; the two worry lists follow. -->
        <section
            class="flex flex-wrap items-end justify-between gap-6 rounded-2xl bg-teal-700 p-6 text-white"
        >
            <div>
                <p class="text-teal-100">{{ $t('Today’s sales') }}</p>
                <p class="tabular font-display text-5xl font-bold">
                    {{ rm(stats.sales_total_sen) }}
                </p>
                <p class="mt-1 text-teal-100">
                    {{
                        $t(
                            stats.sales_count === 1
                                ? ':count receipt'
                                : ':count receipts',
                            { count: stats.sales_count },
                        )
                    }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    as-child
                    class="bg-white text-teal-800 hover:bg-teal-50"
                >
                    <Link :href="pos()"
                        ><ScanBarcode /> {{ $t('Open POS') }}</Link
                    >
                </Button>
                <Button
                    as-child
                    variant="outline"
                    class="border-white/40 bg-transparent text-white hover:bg-white/10 hover:text-white"
                >
                    <Link :href="newReceipt()"
                        ><PackageOpen /> {{ $t('Receive stock') }}</Link
                    >
                </Button>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section
                class="rounded-2xl border border-amber-200 bg-amber-50/60 p-5 dark:border-amber-500/30 dark:bg-amber-500/5"
            >
                <div class="mb-3 flex items-center gap-2">
                    <CalendarClock class="size-5 text-amber-600" />
                    <h2 class="font-semibold">
                        {{ $t('Expiring within 90 days') }}
                    </h2>
                    <span
                        class="ml-auto rounded-full bg-amber-500 px-2 text-sm font-semibold text-white"
                        >{{ stats.near_expiry }}</span
                    >
                </div>
                <p
                    v-if="!nearExpiry.length"
                    class="text-sm text-muted-foreground"
                >
                    {{ $t('Nothing expires in the next 90 days.') }}
                </p>
                <ul
                    class="divide-y divide-amber-200/70 dark:divide-amber-500/20"
                >
                    <li
                        v-for="b in nearExpiry"
                        :key="b.batch_no + b.product"
                        class="flex items-center justify-between gap-3 py-2 text-sm"
                    >
                        <div>
                            <p class="font-medium">{{ b.product }}</p>
                            <p class="text-muted-foreground">
                                {{
                                    $t('Batch :batch, :qty left', {
                                        batch: b.batch_no,
                                        qty: b.qty,
                                    })
                                }}
                            </p>
                        </div>
                        <span
                            :class="[
                                'rounded-md px-2 py-0.5 font-medium',
                                b.expired
                                    ? 'bg-rose-600 text-white'
                                    : 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
                            ]"
                            >{{
                                b.expired
                                    ? $t('Expired')
                                    : formatDate(b.expiry_date)
                            }}</span
                        >
                    </li>
                </ul>
            </section>

            <section
                class="rounded-2xl border border-indigo-200 bg-indigo-50/60 p-5 dark:border-indigo-500/30 dark:bg-indigo-500/5"
            >
                <div class="mb-3 flex items-center gap-2">
                    <PackageOpen class="size-5 text-indigo-600" />
                    <h2 class="font-semibold">
                        {{ $t('At or below reorder level') }}
                    </h2>
                    <span
                        class="ml-auto rounded-full bg-indigo-600 px-2 text-sm font-semibold text-white"
                        >{{ stats.low_stock }}</span
                    >
                </div>
                <p
                    v-if="!lowStock.length"
                    class="text-sm text-muted-foreground"
                >
                    {{ $t('Every product is above its reorder level.') }}
                </p>
                <ul
                    class="divide-y divide-indigo-200/70 dark:divide-indigo-500/20"
                >
                    <li
                        v-for="p in lowStock"
                        :key="p.id"
                        class="flex items-center justify-between py-2 text-sm"
                    >
                        <span class="font-medium">{{ p.name }}</span>
                        <span class="tabular text-muted-foreground"
                            ><b
                                :class="
                                    Number(p.on_hand) === 0
                                        ? 'text-rose-600'
                                        : 'text-indigo-700 dark:text-indigo-300'
                                "
                                >{{ p.on_hand }}</b
                            >
                            {{
                                $t('/ reorder at :level', {
                                    level: p.reorder_level,
                                })
                            }}</span
                        >
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
