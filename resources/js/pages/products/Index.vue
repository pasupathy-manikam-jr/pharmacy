<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FileUp, Pencil, Pill, Plus, Search } from '@lucide/vue';
import { ref } from 'vue';
import Pagination from '@/components/Pagination.vue';
import ViewToggle from '@/components/ViewToggle.vue';
import { useViewMode } from '@/composables/useViewMode';
import PageHeader from '@/components/PageHeader.vue';
import SortableHead from '@/components/SortableHead.vue';
import PoisonBadge from '@/components/PoisonBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { rm } from '@/lib/money';
import {
    create,
    edit,
    importMethod as importPage,
    index,
} from '@/routes/products';
import type { Paginated, Product, SortState } from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Products', href: index() }] },
});

const props = defineProps<{
    sort: SortState;
    products: Paginated<Product>;
    search: string;
}>();

const q = ref(props.search);
const view = useViewMode('products');

const band: Record<string, string> = {
    none: 'bg-emerald-500',
    B: 'bg-rose-500',
    C: 'bg-orange-500',
    D: 'bg-amber-500',
    psychotropic: 'bg-fuchsia-500',
    dda: 'bg-red-600',
};
const submit = () =>
    router.get(
        index().url,
        { search: q.value || undefined },
        { preserveState: true },
    );
</script>

<template>
    <Head title="Products" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <PageHeader
            title="Products"
            description="Your catalogue, prices and poison classification."
            :icon="Pill"
            tone="bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300"
        >
            <form class="relative" novalidate @submit.prevent="submit">
                <Search
                    class="absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                />
                <Input
                    v-model="q"
                    class="w-64 pl-8"
                    placeholder="Name, generic or barcode"
                />
            </form>
            <ViewToggle v-model="view" />
            <Button variant="outline" as-child
                ><Link :href="importPage()"><FileUp /> Import CSV</Link></Button
            >
            <Button as-child class="bg-indigo-600 hover:bg-indigo-700">
                <Link :href="create()"><Plus /> Add product</Link>
            </Button>
        </PageHeader>

        <div v-if="view === 'list'" class="rounded-xl border bg-card">
            <Table>
                <TableHeader>
                    <TableRow>
                        <SortableHead name="name" :sort="sort"
                            >Product</SortableHead
                        >
                        <SortableHead name="generic_name" :sort="sort"
                            >Generic</SortableHead
                        >
                        <SortableHead name="poison_group" :sort="sort"
                            >Class</SortableHead
                        >
                        <SortableHead name="barcode" :sort="sort"
                            >Barcode</SortableHead
                        >
                        <SortableHead
                            name="price_sen"
                            :sort="sort"
                            align="right"
                            >Price</SortableHead
                        >
                        <SortableHead
                            name="reorder_level"
                            :sort="sort"
                            align="right"
                            >Reorder at</SortableHead
                        >
                        <TableHead class="col-action" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!products.data.length">
                        <TableCell
                            colspan="7"
                            class="py-10 text-center text-muted-foreground"
                        >
                            No products yet. Add your first product to start
                            selling.
                        </TableCell>
                    </TableRow>
                    <TableRow
                        v-for="p in products.data"
                        :key="p.id"
                        :class="!p.is_active && 'opacity-50'"
                    >
                        <TableCell class="font-medium">
                            {{ p.name }}
                            <span class="text-muted-foreground"
                                >{{ p.strength }} {{ p.form }}</span
                            >
                        </TableCell>
                        <TableCell class="text-muted-foreground">{{
                            p.generic_name
                        }}</TableCell>
                        <TableCell
                            ><PoisonBadge :group="p.poison_group"
                        /></TableCell>
                        <TableCell class="text-muted-foreground">{{
                            p.barcode
                        }}</TableCell>
                        <TableCell class="text-right">{{
                            rm(p.price_sen)
                        }}</TableCell>
                        <TableCell class="text-right">{{
                            p.reorder_level
                        }}</TableCell>
                        <TableCell class="col-action text-right">
                            <Button variant="ghost" size="icon" as-child>
                                <Link
                                    :href="edit(p.id)"
                                    :aria-label="`Edit ${p.name}`"
                                    ><Pencil
                                /></Link>
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
        <div
            v-if="view === 'grid'"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4"
        >
            <p
                v-if="!products.data.length"
                class="col-span-full rounded-xl border border-dashed p-10 text-center text-muted-foreground"
            >
                No products yet. Add your first product to start selling.
            </p>
            <Link
                v-for="p in products.data"
                :key="p.id"
                :href="edit(p.id)"
                :class="[
                    'group relative flex flex-col overflow-hidden rounded-xl border bg-card pt-1.5 transition-colors hover:border-indigo-300 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none dark:hover:border-indigo-500/50',
                    !p.is_active && 'opacity-50',
                ]"
            >
                <span
                    :class="[
                        'absolute inset-x-0 top-0 h-1.5',
                        band[p.poison_group],
                    ]"
                />
                <div class="flex flex-1 flex-col gap-1 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="font-semibold">{{ p.name }}</h2>
                        <PoisonBadge :group="p.poison_group" />
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ [p.strength, p.form].filter(Boolean).join(' ') }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ p.generic_name }}
                    </p>
                    <div class="mt-auto flex items-end justify-between pt-3">
                        <span class="tabular font-display text-2xl font-bold"
                            >{{ rm(p.price_sen)
                            }}<span
                                class="text-sm font-normal text-muted-foreground"
                            >
                                / {{ p.unit }}</span
                            ></span
                        >
                        <Pencil
                            class="size-4 text-muted-foreground group-hover:text-indigo-600"
                        />
                    </div>
                    <p
                        v-if="p.barcode"
                        class="tabular text-xs text-muted-foreground"
                    >
                        {{ p.barcode }}
                    </p>
                </div>
            </Link>
        </div>
        <Pagination :page="products" />
    </div>
</template>
