<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Download, FileUp } from '@lucide/vue';
import ProductImportController from '@/actions/App/Http/Controllers/Pharmacy/ProductImportController';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/products';
import { template } from '@/routes/products/import';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Products', href: index() },
            { title: 'Import', href: '#' },
        ],
    },
});

defineProps<{ columns: string[] }>();

const form = useForm({ file: null as File | null });
const submit = () =>
    form.post(ProductImportController.store.url(), { forceFormData: true });
const errors = () => {
    const e = (form.errors as Record<string, string | string[]>).file;
    return Array.isArray(e) ? e : e ? [e] : [];
};
</script>

<template>
    <Head title="Import products" />

    <div class="flex max-w-3xl flex-col gap-5 p-4 md:p-6">
        <PageHeader
            :back="{ href: index(), label: 'products' }"
            title="Import products"
            description="Add or update many products at once from a spreadsheet saved as CSV."
            :icon="FileUp"
            tone="bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300"
        >
            <Button variant="outline" as-child
                ><a :href="template().url"
                    ><Download /> Download template</a
                ></Button
            >
        </PageHeader>

        <form
            class="grid gap-4 rounded-xl border bg-card p-5"
            novalidate
            @submit.prevent="submit"
        >
            <div class="grid gap-1.5">
                <Label for="file">CSV file</Label>
                <Input
                    id="file"
                    type="file"
                    accept=".csv,text/csv"
                    @input="
                        form.file =
                            ($event.target as HTMLInputElement).files?.[0] ??
                            null
                    "
                />
            </div>
            <ul
                v-if="errors().length"
                class="list-disc rounded-lg bg-rose-50 p-3 pl-8 text-sm text-rose-800 dark:bg-rose-500/10 dark:text-rose-300"
            >
                <li v-for="e in errors()" :key="e">{{ e }}</li>
            </ul>
            <div class="text-sm text-muted-foreground">
                <p>
                    Columns:
                    <span class="text-foreground">{{ columns.join(', ') }}</span
                    >. Only name and price are required.
                </p>
                <p class="mt-1">
                    Rows with a barcode update the product with that barcode;
                    others match on name and strength. Nothing is saved if any
                    row has a problem.
                </p>
                <p class="mt-1">
                    poison_group is one of none, B, C, D, psychotropic, dda.
                    Prices are in ringgit, tax_rate in percent.
                </p>
            </div>
            <Button
                :disabled="form.processing || !form.file"
                class="self-start bg-indigo-600 hover:bg-indigo-700"
                >Import products</Button
            >
        </form>
    </div>
</template>
