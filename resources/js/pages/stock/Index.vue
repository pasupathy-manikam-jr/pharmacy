<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import StockController from '@/actions/App/Http/Controllers/Pharmacy/StockController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    ArrowLeftRight,
    Boxes,
    History,
    Search,
    SlidersHorizontal,
} from '@lucide/vue';
import { ref } from 'vue';
import Pagination from '@/components/Pagination.vue';
import PageHeader from '@/components/PageHeader.vue';
import SortableHead from '@/components/SortableHead.vue';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate, rm } from '@/lib/money';
import { index, movements, transfer } from '@/routes/stock';
import type { Paginated, SortState } from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Stock', href: index() }] },
});

type Level = {
    id: number;
    batch_id: number;
    product: string;
    unit: string;
    batch_no: string;
    expiry_date: string;
    days_left: number;
    qty: number;
    cost_sen: number;
};

const props = defineProps<{
    sort: SortState;
    levels: Paginated<Level>;
    search: string;
    reasons: Record<string, string>;
    canAdjust: boolean;
}>();

const adjusting = ref<Level | null>(null);
const adj = useForm({ reason: 'count', counted: '', qty: '', note: '' });
function startAdjust(l: Level) {
    adj.reset();
    adj.clearErrors();
    adj.counted = String(l.qty);
    adjusting.value = l;
}
const saveAdjust = () =>
    adjusting.value &&
    adj
        .transform((d) => ({
            reason: d.reason,
            counted:
                d.reason === 'count'
                    ? Number.parseInt(d.counted || '0', 10)
                    : null,
            qty:
                d.reason === 'count' ? null : Number.parseInt(d.qty || '0', 10),
            note: d.note || null,
        }))
        .post(StockController.adjust.url(adjusting.value.batch_id), {
            preserveScroll: true,
            onSuccess: () => (adjusting.value = null),
        });

const q = ref(props.search);
const submit = () =>
    router.get(
        index().url,
        { search: q.value || undefined },
        { preserveState: true },
    );

const tone = (days: number) =>
    days <= 0
        ? 'bg-rose-600 text-white'
        : days <= 90
          ? 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300'
          : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300';
</script>

<template>
    <Head title="Stock" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <PageHeader
            title="Stock on hand"
            description="By batch, soonest expiry first. Sales take from the top of each product."
            :icon="Boxes"
            tone="bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300"
        >
            <form class="relative" novalidate @submit.prevent="submit">
                <Search
                    class="absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                />
                <Input
                    v-model="q"
                    class="w-64 pl-8"
                    placeholder="Product name"
                />
            </form>
        </PageHeader>

        <div class="rounded-xl border bg-card">
            <Table>
                <TableHeader>
                    <TableRow>
                        <SortableHead name="product" :sort="sort"
                            >Product</SortableHead
                        >
                        <SortableHead name="batch_no" :sort="sort"
                            >Batch</SortableHead
                        >
                        <SortableHead name="expiry_date" :sort="sort"
                            >Expiry</SortableHead
                        >
                        <SortableHead name="qty" :sort="sort" align="right"
                            >On hand</SortableHead
                        >
                        <SortableHead name="cost_sen" :sort="sort" align="right"
                            >Unit cost</SortableHead
                        >
                        <SortableHead
                            name="value_sen"
                            :sort="sort"
                            align="right"
                            >Value</SortableHead
                        >
                        <TableHead v-if="canAdjust" class="col-action" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!levels.data.length">
                        <TableCell
                            colspan="6"
                            class="py-10 text-center text-muted-foreground"
                        >
                            No stock yet. Receive goods to add batches.
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="l in levels.data" :key="l.id">
                        <TableCell class="font-medium">{{
                            l.product
                        }}</TableCell>
                        <TableCell class="text-muted-foreground">{{
                            l.batch_no
                        }}</TableCell>
                        <TableCell>
                            <span
                                :class="[
                                    'rounded-md px-2 py-0.5 text-sm font-medium',
                                    tone(l.days_left),
                                ]"
                            >
                                {{
                                    l.days_left <= 0
                                        ? 'Expired'
                                        : formatDate(l.expiry_date)
                                }}
                            </span>
                        </TableCell>
                        <TableCell class="text-right font-semibold"
                            >{{ l.qty }}
                            <span class="font-normal text-muted-foreground">{{
                                l.unit
                            }}</span></TableCell
                        >
                        <TableCell class="text-right">{{
                            rm(l.cost_sen)
                        }}</TableCell>
                        <TableCell class="text-right">{{
                            rm(l.cost_sen * l.qty)
                        }}</TableCell>
                        <TableCell
                            v-if="canAdjust"
                            class="col-action text-right"
                        >
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="startAdjust(l)"
                                ><SlidersHorizontal /> Adjust</Button
                            >
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
        <Pagination :page="levels" />

        <Dialog
            :open="adjusting !== null"
            @update:open="(o) => !o && (adjusting = null)"
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Adjust {{ adjusting?.product }}</DialogTitle>
                    <DialogDescription
                        >Batch {{ adjusting?.batch_no }},
                        {{ adjusting?.qty }} on hand. Every adjustment is logged
                        with its reason.</DialogDescription
                    >
                </DialogHeader>
                <form
                    class="grid gap-4"
                    novalidate
                    @submit.prevent="saveAdjust"
                >
                    <div class="grid gap-1.5">
                        <Label for="reason">Reason</Label>
                        <Select v-model="adj.reason">
                            <SelectTrigger id="reason" class="w-full"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="(label, key) in reasons"
                                    :key="key"
                                    :value="key"
                                    >{{ label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <div v-if="adj.reason === 'count'" class="grid gap-1.5">
                        <Label for="counted">Counted on the shelf</Label>
                        <Input
                            id="counted"
                            v-model="adj.counted"
                            inputmode="numeric"
                        />
                        <InputError :message="adj.errors.counted" />
                    </div>
                    <div v-else class="grid gap-1.5">
                        <Label for="qty">Quantity to remove</Label>
                        <Input id="qty" v-model="adj.qty" inputmode="numeric" />
                        <InputError
                            :message="
                                adj.errors.qty ??
                                (adj.errors as Record<string, string>).stock
                            "
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="note">Note</Label>
                        <Input id="note" v-model="adj.note" />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="adjusting = null"
                            >Cancel</Button
                        >
                        <Button
                            :disabled="adj.processing"
                            class="bg-amber-600 hover:bg-amber-700"
                            >Save adjustment</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
