<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Pill } from '@lucide/vue';
import ProductController from '@/actions/App/Http/Controllers/Pharmacy/ProductController';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { fromSen, toSen } from '@/lib/money';
import { index } from '@/routes/products';
import type { PoisonGroup, Product } from '@/types';

const props = defineProps<{
    product: Product | null;
    poisonGroups: PoisonGroup[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Products', href: index() }] },
});

const groupLabels: Record<PoisonGroup, string> = {
    none: 'Not a poison (OTC / general)',
    B: 'Group B (prescription only)',
    C: 'Group C (pharmacist only)',
    D: 'Group D',
    psychotropic: 'Psychotropic',
    dda: 'Dangerous Drugs Act',
};

const p = props.product;
const form = useForm({
    name: p?.name ?? '',
    generic_name: p?.generic_name ?? '',
    strength: p?.strength ?? '',
    form: p?.form ?? '',
    poison_group: p?.poison_group ?? ('none' as PoisonGroup),
    barcode: p?.barcode ?? '',
    mal_reg_no: p?.mal_reg_no ?? '',
    unit: p?.unit ?? 'unit',
    price: p ? fromSen(p.price_sen) : '',
    tax_rate: p ? String(p.tax_rate_bp / 100) : '0',
    reorder_level: String(p?.reorder_level ?? 0),
    is_active: p?.is_active ?? true,
});

// Server validates the transformed field names (price_sen, tax_rate_bp).
const errors = computed(() => form.errors as Record<string, string>);

function submit() {
    form.transform(({ price, tax_rate, reorder_level, ...rest }) => ({
        ...rest,
        price_sen: toSen(price),
        tax_rate_bp: Math.round(Number.parseFloat(tax_rate || '0') * 100),
        reorder_level: Number.parseInt(reorder_level || '0', 10),
    }));

    if (p) {
        form.put(ProductController.update.url(p.id));
    } else {
        form.post(ProductController.store.url());
    }
}
</script>

<template>
    <Head :title="p ? `Edit ${p.name}` : 'Add product'" />

    <div class="flex max-w-3xl flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="p ? `Edit ${p.name}` : 'Add product'"
            description="Prices are per unit, including the smallest unit you sell."
            :icon="Pill"
            tone="bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300"
        />

        <form
            class="grid gap-5 rounded-xl border bg-card p-5 sm:grid-cols-2"
            novalidate
            @submit.prevent="submit"
        >
            <div class="grid gap-2 sm:col-span-2">
                <Label for="name">Brand / product name</Label>
                <Input id="name" v-model="form.name" placeholder="Panadol" />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="generic_name">Generic name</Label>
                <Input
                    id="generic_name"
                    v-model="form.generic_name"
                    placeholder="Paracetamol"
                />
                <InputError :message="form.errors.generic_name" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="grid gap-2">
                    <Label for="strength">Strength</Label>
                    <Input
                        id="strength"
                        v-model="form.strength"
                        placeholder="500 mg"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="form">Form</Label>
                    <Input id="form" v-model="form.form" placeholder="Tablet" />
                </div>
            </div>
            <div class="grid gap-2 sm:col-span-2">
                <Label for="poison_group">Poison classification</Label>
                <Select v-model="form.poison_group">
                    <SelectTrigger id="poison_group" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="g in poisonGroups"
                            :key="g"
                            :value="g"
                        >
                            {{ groupLabels[g] }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p class="text-sm text-muted-foreground">
                    Anything other than “not a poison” needs a pharmacist at the
                    counter and is written to a register.
                </p>
                <InputError :message="form.errors.poison_group" />
            </div>
            <div class="grid gap-2">
                <Label for="barcode">Barcode</Label>
                <Input
                    id="barcode"
                    v-model="form.barcode"
                    placeholder="Scan or type"
                />
                <InputError :message="form.errors.barcode" />
            </div>
            <div class="grid gap-2">
                <Label for="mal_reg_no">MAL registration no.</Label>
                <Input
                    id="mal_reg_no"
                    v-model="form.mal_reg_no"
                    placeholder="MAL19990001A"
                />
            </div>
            <div class="grid gap-2">
                <Label for="price">Selling price (RM)</Label>
                <Input
                    id="price"
                    v-model="form.price"
                    inputmode="decimal"
                    placeholder="0.00"
                />
                <InputError :message="errors.price_sen" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="grid gap-2">
                    <Label for="unit">Unit</Label>
                    <Input id="unit" v-model="form.unit" placeholder="tablet" />
                    <InputError :message="form.errors.unit" />
                </div>
                <div class="grid gap-2">
                    <Label for="tax_rate">Tax (%)</Label>
                    <Input
                        id="tax_rate"
                        v-model="form.tax_rate"
                        inputmode="decimal"
                    />
                    <InputError :message="errors.tax_rate_bp" />
                </div>
            </div>
            <div class="grid gap-2">
                <Label for="reorder_level">Reorder when stock reaches</Label>
                <Input
                    id="reorder_level"
                    v-model="form.reorder_level"
                    inputmode="numeric"
                />
                <InputError :message="form.errors.reorder_level" />
            </div>
            <div class="flex items-center gap-2 self-end pb-2">
                <Checkbox id="is_active" v-model="form.is_active" />
                <Label for="is_active">Available for sale</Label>
            </div>
            <div class="sm:col-span-2">
                <Button
                    :disabled="form.processing"
                    class="bg-indigo-600 hover:bg-indigo-700"
                >
                    {{ p ? 'Save changes' : 'Add product' }}
                </Button>
            </div>
        </form>
    </div>
</template>
