<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FileUp, Pencil, Pill, Plus, Search } from '@lucide/vue';
import { ref } from 'vue';
import Pagination from '@/components/Pagination.vue';
import PageHeader from '@/components/PageHeader.vue';
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
import type { Paginated, Product } from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Products', href: index() }] },
});

const props = defineProps<{ products: Paginated<Product>; search: string }>();

const q = ref(props.search);
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
            <Button variant="outline" as-child
                ><Link :href="importPage()"><FileUp /> Import CSV</Link></Button
            >
            <Button as-child class="bg-indigo-600 hover:bg-indigo-700">
                <Link :href="create()"><Plus /> Add product</Link>
            </Button>
        </PageHeader>

        <div class="rounded-xl border bg-card">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Product</TableHead>
                        <TableHead>Generic</TableHead>
                        <TableHead>Class</TableHead>
                        <TableHead>Barcode</TableHead>
                        <TableHead class="text-right">Price</TableHead>
                        <TableHead class="text-right">Reorder at</TableHead>
                        <TableHead />
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
                        <TableCell class="text-right">
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
        <Pagination :page="products" />
    </div>
</template>
