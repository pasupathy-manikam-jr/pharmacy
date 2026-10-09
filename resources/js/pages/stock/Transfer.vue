<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeftRight, Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import StockController from '@/actions/App/Http/Controllers/Pharmacy/StockController';
import Combobox from '@/components/Combobox.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as stock, transfer } from '@/routes/stock';
import type { Option } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Stock', href: stock() },
            { title: 'Transfer', href: transfer() },
        ],
    },
});

const props = defineProps<{
    branches: { id: number; name: string }[];
    batches: (Option & { qty: number })[];
}>();

type Line = { batch_id: number | null; qty: string };
const form = useForm({
    to_branch_id: null as string | null,
    note: '',
    lines: [{ batch_id: null, qty: '' }] as Line[],
});
const options = computed<Option[]>(() =>
    props.batches.map(({ value, label, hint }) => ({ value, label, hint })),
);
const err = (i: number, f: string) =>
    (form.errors as Record<string, string>)[`lines.${i}.${f}`];

const submit = () =>
    form
        .transform((d) => ({
            to_branch_id: d.to_branch_id ? Number(d.to_branch_id) : null,
            note: d.note || null,
            lines: d.lines.map((l) => ({
                batch_id: l.batch_id,
                qty: Number.parseInt(l.qty || '0', 10),
            })),
        }))
        .post(StockController.storeTransfer.url());
</script>

<template>
    <Head title="Transfer stock" />

    <form
        class="flex max-w-3xl flex-col gap-5 p-4 md:p-6"
        novalidate
        @submit.prevent="submit"
    >
        <PageHeader
            :back="{ href: stock(), label: 'stock' }"
            title="Transfer stock"
            description="Send batches to another branch. Expiry dates travel with the batch."
            :icon="ArrowLeftRight"
            tone="bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-300"
        />

        <p
            v-if="!branches.length"
            class="rounded-xl border border-dashed p-6 text-muted-foreground"
        >
            There’s only one branch. Add another under Branches to transfer
            stock.
        </p>

        <template v-else>
            <div
                class="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-2"
            >
                <div class="grid gap-1.5">
                    <Label for="to">Send to</Label>
                    <Select v-model="form.to_branch_id">
                        <SelectTrigger id="to" class="w-full"
                            ><SelectValue placeholder="Choose branch"
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="b in branches"
                                :key="b.id"
                                :value="String(b.id)"
                                >{{ b.name }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.to_branch_id" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="note">Note</Label>
                    <Input
                        id="note"
                        v-model="form.note"
                        placeholder="e.g. Weekly top-up"
                    />
                </div>
            </div>

            <div class="rounded-xl border bg-card p-5">
                <div
                    v-for="(line, i) in form.lines"
                    :key="i"
                    class="grid gap-3 border-t py-3 first:border-t-0 sm:grid-cols-[1fr_7rem_auto]"
                >
                    <div>
                        <Combobox
                            v-model="line.batch_id"
                            :options="options"
                            placeholder="Choose batch"
                            search-placeholder="Product or batch"
                        />
                        <InputError :message="err(i, 'batch_id')" />
                    </div>
                    <div>
                        <Input
                            v-model="line.qty"
                            inputmode="numeric"
                            placeholder="Qty"
                        />
                        <InputError :message="err(i, 'qty')" />
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :disabled="form.lines.length === 1"
                        aria-label="Remove line"
                        @click="form.lines.splice(i, 1)"
                        ><Trash2 class="text-rose-500"
                    /></Button>
                </div>
                <InputError
                    :message="
                        (form.errors as Record<string, string>).stock ??
                        form.errors.lines
                    "
                />
                <div class="mt-3 flex justify-between border-t pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="form.lines.push({ batch_id: null, qty: '' })"
                        ><Plus /> Add line</Button
                    >
                    <Button
                        :disabled="form.processing"
                        class="bg-orange-600 hover:bg-orange-700"
                        >Transfer stock</Button
                    >
                </div>
            </div>
        </template>
    </form>
</template>
