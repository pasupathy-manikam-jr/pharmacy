<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Truck } from '@lucide/vue';
import SupplierController from '@/actions/App/Http/Controllers/Pharmacy/SupplierController';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import SortableHead from '@/components/SortableHead.vue';
import ViewToggle from '@/components/ViewToggle.vue';
import { useViewMode } from '@/composables/useViewMode';
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
import { index } from '@/routes/suppliers';
import type { Supplier, SortState } from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Suppliers', href: index() }] },
});

defineProps<{
    sort: SortState;
    suppliers: Supplier[];
}>();

const view = useViewMode('suppliers', 'grid');

const form = useForm({ name: '', tin: '', phone: '', email: '', address: '' });
const submit = () =>
    form.post(SupplierController.store.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
</script>

<template>
    <Head title="Suppliers" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <PageHeader
            title="Suppliers"
            description="Wholesalers you receive stock from."
            :icon="Truck"
            tone="bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-300"
        >
            <ViewToggle v-model="view" />
        </PageHeader>

        <form
            class="grid gap-3 rounded-xl border border-orange-200 bg-orange-50/50 p-4 sm:grid-cols-6 dark:border-orange-500/30 dark:bg-orange-500/5"
            novalidate
            @submit.prevent="submit"
        >
            <div class="grid gap-1.5 sm:col-span-2">
                <Label for="name">Name</Label>
                <Input id="name" v-model="form.name" />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-1.5">
                <Label for="tin">TIN</Label>
                <Input id="tin" v-model="form.tin" />
            </div>
            <div class="grid gap-1.5">
                <Label for="phone">Phone</Label>
                <Input id="phone" v-model="form.phone" />
            </div>
            <div class="grid gap-1.5">
                <Label for="email">Email</Label>
                <Input id="email" v-model="form.email" />
                <InputError :message="form.errors.email" />
            </div>
            <div class="flex items-end">
                <Button
                    :disabled="form.processing"
                    class="w-full bg-orange-600 hover:bg-orange-700"
                    >Add supplier</Button
                >
            </div>
        </form>

        <div v-if="view === 'list'" class="rounded-xl border bg-card">
            <Table>
                <TableHeader>
                    <TableRow>
                        <SortableHead name="name" :sort="sort"
                            >Name</SortableHead
                        >
                        <SortableHead name="tin" :sort="sort">TIN</SortableHead>
                        <SortableHead name="phone" :sort="sort"
                            >Phone</SortableHead
                        >
                        <SortableHead name="email" :sort="sort"
                            >Email</SortableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!suppliers.length">
                        <TableCell
                            colspan="4"
                            class="py-10 text-center text-muted-foreground"
                        >
                            Add a supplier above before receiving stock.
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="s in suppliers" :key="s.id">
                        <TableCell class="font-medium">{{ s.name }}</TableCell>
                        <TableCell>{{ s.tin }}</TableCell>
                        <TableCell>{{ s.phone }}</TableCell>
                        <TableCell>{{ s.email }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
        <div
            v-if="view === 'grid'"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
        >
            <p
                v-if="!suppliers.length"
                class="col-span-full rounded-xl border border-dashed p-10 text-center text-muted-foreground"
            >
                Add a supplier above before receiving stock.
            </p>
            <section
                v-for="s in suppliers"
                :key="s.id"
                class="rounded-xl border border-l-4 border-l-orange-500 bg-card p-4"
            >
                <h2 class="font-semibold">{{ s.name }}</h2>
                <p v-if="s.tin" class="text-sm text-muted-foreground">
                    TIN {{ s.tin }}
                </p>
                <p class="mt-2 text-sm">{{ s.phone }}</p>
                <a
                    v-if="s.email"
                    :href="`mailto:${s.email}`"
                    class="text-sm text-orange-700 hover:underline dark:text-orange-300"
                    >{{ s.email }}</a
                >
                <p v-if="s.address" class="mt-1 text-sm text-muted-foreground">
                    {{ s.address }}
                </p>
            </section>
        </div>
    </div>
</template>
