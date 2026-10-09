<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    Banknote,
    CreditCard,
    Minus,
    NotebookPen,
    Plus,
    ScanBarcode,
    Smartphone,
    TriangleAlert,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import PosController from '@/actions/App/Http/Controllers/Pharmacy/PosController';
import Combobox from '@/components/Combobox.vue';
import DatePicker from '@/components/DatePicker.vue';
import InputError from '@/components/InputError.vue';
import OpenShiftForm from '@/components/OpenShiftForm.vue';
import PoisonBadge from '@/components/PoisonBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { rm, toSen } from '@/lib/money';
import { index } from '@/routes/pos';
import type { Option, PoisonGroup } from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Point of sale', href: index() }] },
});

type PosProduct = {
    id: number;
    name: string;
    generic_name: string | null;
    strength: string | null;
    barcode: string | null;
    price_sen: number;
    tax_rate_bp: number;
    poison_group: PoisonGroup;
    on_hand: number | string;
};
type PosCustomer = {
    id: number;
    name: string;
    ic_no: string | null;
    allergies: string | null;
};

type Refillable = {
    id: number;
    customer_id: number;
    label: string;
    remaining: number;
};

const props = defineProps<{
    products: PosProduct[];
    customers: PosCustomer[];
    prescriptions: Refillable[];
    isPharmacist: boolean;
    shift: { id: number; opened_at: string } | null;
}>();

const rxGroups: PoisonGroup[] = ['B', 'psychotropic', 'dda'];

// ---- search & scan ---------------------------------------------------------
const query = ref('');
const results = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return [];
    return props.products
        .filter((p) =>
            [p.name, p.generic_name, p.barcode].some((v) =>
                v?.toLowerCase().includes(q),
            ),
        )
        .slice(0, 12);
});

// ---- cart ------------------------------------------------------------------
type CartLine = { product: PosProduct; qty: number; dosage: string };
const cart = ref<CartLine[]>([]);

function add(product: PosProduct) {
    const line = cart.value.find((l) => l.product.id === product.id);
    if (line) line.qty++;
    else cart.value.push({ product, qty: 1, dosage: '' });
    query.value = '';
}

/** A keyboard-wedge scanner types the barcode then Enter: exact barcode wins. */
function onEnter() {
    const q = query.value.trim();
    const exact = props.products.find((p) => p.barcode && p.barcode === q);
    const pick =
        exact ?? (results.value.length === 1 ? results.value[0] : undefined);
    if (pick) add(pick);
}

const hasPoison = computed(() =>
    cart.value.some((l) => l.product.poison_group !== 'none'),
);
const needsRx = computed(() =>
    cart.value.some((l) => rxGroups.includes(l.product.poison_group)),
);

// ---- customer --------------------------------------------------------------
const customerOptions = computed<Option[]>(() =>
    props.customers.map((c) => ({
        value: c.id,
        label: c.name,
        hint: c.ic_no ?? undefined,
    })),
);
const selectedCustomer = computed(() =>
    props.customers.find((c) => c.id === form.customer_id),
);

const allergyHits = computed(() => {
    const allergies = (selectedCustomer.value?.allergies ?? '')
        .split(/[,;/]/)
        .map((a) => a.trim().toLowerCase())
        .filter(Boolean);
    return cart.value.filter((l) =>
        allergies.some((a) =>
            [l.product.name, l.product.generic_name].some((v) =>
                v?.toLowerCase().includes(a),
            ),
        ),
    );
});

// ---- totals & payment ------------------------------------------------------
const form = useForm({
    customer_id: null as number | null,
    customer: { name: '', ic_no: '', address: '' },
    prescription_id: null as number | null,
    prescription: {
        refills_allowed: '0',
        prescriber_name: '',
        prescriber_reg_no: '',
        clinic: '',
        diagnosis: '',
        issued_on: null as string | null,
    },
    discount: '',
    payment_method: 'cash' as 'cash' | 'card' | 'ewallet' | 'credit',
    tendered: '',
});

const subtotal = computed(() =>
    cart.value.reduce((s, l) => s + l.product.price_sen * l.qty, 0),
);
const tax = computed(() =>
    cart.value.reduce(
        (s, l) =>
            s +
            Math.round(
                (l.product.price_sen * l.qty * l.product.tax_rate_bp) / 10000,
            ),
        0,
    ),
);
const total = computed(() => subtotal.value + tax.value - toSen(form.discount));
const change = computed(() => toSen(form.tendered) - total.value);

const methods = [
    {
        value: 'cash',
        label: 'Cash',
        icon: Banknote,
        tone: 'data-[on=true]:bg-emerald-600 data-[on=true]:border-emerald-600',
    },
    {
        value: 'card',
        label: 'Card',
        icon: CreditCard,
        tone: 'data-[on=true]:bg-sky-600 data-[on=true]:border-sky-600',
    },
    {
        value: 'ewallet',
        label: 'E-wallet',
        icon: Smartphone,
        tone: 'data-[on=true]:bg-fuchsia-600 data-[on=true]:border-fuchsia-600',
    },
    {
        value: 'credit',
        label: 'On account',
        icon: NotebookPen,
        tone: 'data-[on=true]:bg-amber-600 data-[on=true]:border-amber-600',
    },
] as const;

const errors = computed(() => form.errors as Record<string, string>);

const refillOptions = computed<Option[]>(() =>
    props.prescriptions
        .filter((p) => p.customer_id === form.customer_id)
        .map((p) => ({
            value: p.id,
            label: p.label,
            hint: `${p.remaining} left`,
        })),
);

function charge() {
    form.transform((data) => ({
        customer_id: data.customer_id,
        customer: data.customer_id ? null : data.customer,
        prescription_id: needsRx.value ? data.prescription_id : null,
        prescription:
            needsRx.value && !data.prescription_id
                ? {
                      ...data.prescription,
                      refills_allowed: Number.parseInt(
                          data.prescription.refills_allowed || '0',
                          10,
                      ),
                  }
                : null,
        lines: cart.value.map((l) => ({
            product_id: l.product.id,
            qty: l.qty,
            dosage: l.dosage || null,
        })),
        discount_sen: toSen(data.discount),
        payment_method: data.payment_method,
        tendered_sen:
            data.payment_method === 'cash' ? toSen(data.tendered) : null,
    })).post(PosController.store.url());
}
</script>

<template>
    <Head title="Point of sale" />

    <div v-if="!shift" class="p-4 md:p-6">
        <section
            class="max-w-xl rounded-2xl border border-green-200 bg-green-50/60 p-6 dark:border-green-500/30 dark:bg-green-500/5"
        >
            <h1 class="text-xl font-semibold">
                Open your shift to start selling
            </h1>
            <p class="mt-1 mb-5 text-muted-foreground">
                Count the float in the drawer. You’ll count it again when you
                close.
            </p>
            <OpenShiftForm />
        </section>
    </div>

    <div v-else class="grid flex-1 gap-5 p-4 md:p-6 xl:grid-cols-[1fr_26rem]">
        <!-- Left: find products -->
        <section class="flex flex-col gap-4">
            <div class="relative">
                <ScanBarcode
                    class="absolute top-3.5 left-3.5 size-5 text-violet-500"
                />
                <Input
                    v-model="query"
                    v-focus
                    class="h-12 rounded-xl border-violet-200 pl-11 text-base focus-visible:border-violet-500 focus-visible:ring-violet-500/30 dark:border-violet-500/30"
                    placeholder="Scan a barcode or type a product name"
                    @keydown.enter.prevent="onEnter"
                />
            </div>

            <ul
                v-if="results.length"
                class="overflow-hidden rounded-xl border bg-card"
            >
                <li v-for="p in results" :key="p.id">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 border-b px-4 py-3 text-left last:border-b-0 hover:bg-violet-50 focus-visible:bg-violet-50 focus-visible:outline-none disabled:opacity-50 dark:hover:bg-violet-500/10"
                        :disabled="Number(p.on_hand) <= 0"
                        @click="add(p)"
                    >
                        <div class="flex-1">
                            <p class="font-medium">
                                {{ p.name }}
                                <span class="text-muted-foreground">{{
                                    p.strength
                                }}</span>
                                <PoisonBadge
                                    :group="p.poison_group"
                                    class="ml-1"
                                />
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ p.generic_name }}
                            </p>
                        </div>
                        <span
                            :class="[
                                'tabular text-sm',
                                Number(p.on_hand) > 0
                                    ? 'text-muted-foreground'
                                    : 'text-rose-600',
                            ]"
                        >
                            {{
                                Number(p.on_hand) > 0
                                    ? `${p.on_hand} in stock`
                                    : 'Out of stock'
                            }}
                        </span>
                        <span class="tabular w-24 text-right font-semibold">{{
                            rm(p.price_sen)
                        }}</span>
                    </button>
                </li>
            </ul>
            <p
                v-else-if="query"
                class="rounded-xl border border-dashed p-6 text-center text-muted-foreground"
            >
                No product matches “{{ query }}”.
            </p>

            <!-- Cart -->
            <div class="rounded-xl border bg-card">
                <div
                    v-if="!cart.length"
                    class="p-10 text-center text-muted-foreground"
                >
                    Scan an item to start a sale.
                </div>
                <div
                    v-for="(line, i) in cart"
                    :key="line.product.id"
                    :class="[
                        'flex flex-wrap items-center gap-3 border-b px-4 py-3 last:border-b-0',
                        line.product.poison_group !== 'none' &&
                            'border-l-4 border-l-rose-500',
                    ]"
                >
                    <div class="min-w-48 flex-1">
                        <p class="font-medium">
                            {{ line.product.name }}
                            <span class="text-muted-foreground">{{
                                line.product.strength
                            }}</span>
                            <PoisonBadge
                                :group="line.product.poison_group"
                                class="ml-1"
                            />
                        </p>
                        <Input
                            v-if="line.product.poison_group !== 'none'"
                            v-model="line.dosage"
                            class="mt-2 h-8"
                            placeholder="Directions, e.g. 1 tab 3x daily after food"
                        />
                    </div>
                    <div class="flex items-center gap-1">
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            class="size-8"
                            aria-label="Decrease"
                            :disabled="line.qty <= 1"
                            @click="line.qty--"
                            ><Minus
                        /></Button>
                        <span class="tabular w-10 text-center font-semibold">{{
                            line.qty
                        }}</span>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            class="size-8"
                            aria-label="Increase"
                            :disabled="line.qty >= Number(line.product.on_hand)"
                            @click="line.qty++"
                            ><Plus
                        /></Button>
                    </div>
                    <span class="tabular w-24 text-right font-semibold">{{
                        rm(line.product.price_sen * line.qty)
                    }}</span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        :aria-label="`Remove ${line.product.name}`"
                        @click="cart.splice(i, 1)"
                        ><X class="text-rose-500"
                    /></Button>
                </div>
            </div>
            <InputError :message="errors.stock ?? errors.lines" />
        </section>

        <!-- Right: who, how, how much -->
        <aside class="flex flex-col gap-4">
            <div
                v-if="hasPoison && !isPharmacist"
                class="flex gap-2 rounded-xl bg-rose-600 p-4 text-sm text-white"
            >
                <TriangleAlert class="size-5 shrink-0" />
                This sale has a scheduled poison. A pharmacist must log in to
                complete it.
            </div>

            <div class="flex flex-col gap-3 rounded-xl border bg-card p-4">
                <h2 class="font-semibold">Customer</h2>
                <Combobox
                    v-model="form.customer_id"
                    :options="customerOptions"
                    placeholder="Walk-in customer"
                    search-placeholder="Name or MyKad"
                />
                <div v-if="!form.customer_id && hasPoison" class="grid gap-2">
                    <p class="text-sm text-muted-foreground">
                        Or record a new customer for the register:
                    </p>
                    <Input
                        v-model="form.customer.name"
                        placeholder="Full name"
                    />
                    <InputError :message="errors['customer.name']" />
                    <Input
                        v-model="form.customer.ic_no"
                        placeholder="MyKad / passport"
                    />
                    <Input
                        v-model="form.customer.address"
                        placeholder="Address"
                    />
                </div>
                <div
                    v-if="selectedCustomer?.allergies"
                    class="rounded-lg bg-rose-50 p-3 text-sm text-rose-800 dark:bg-rose-500/10 dark:text-rose-300"
                >
                    <b>Allergies:</b> {{ selectedCustomer.allergies }}
                    <p
                        v-for="hit in allergyHits"
                        :key="hit.product.id"
                        class="mt-1 font-semibold"
                    >
                        Check {{ hit.product.name }} before dispensing.
                    </p>
                </div>
            </div>

            <div
                v-if="needsRx"
                class="flex flex-col gap-3 rounded-xl border border-rose-200 bg-rose-50/50 p-4 dark:border-rose-500/30 dark:bg-rose-500/5"
            >
                <h2 class="font-semibold text-rose-800 dark:text-rose-300">
                    Prescription
                </h2>
                <Input
                    v-model="form.prescription.prescriber_name"
                    placeholder="Prescriber name"
                />
                <InputError :message="errors['prescription.prescriber_name']" />
                <div class="grid grid-cols-2 gap-2">
                    <Input
                        v-model="form.prescription.prescriber_reg_no"
                        placeholder="MMC no."
                    />
                    <DatePicker
                        v-model="form.prescription.issued_on"
                        placeholder="Date issued"
                    />
                </div>
                <InputError :message="errors['prescription.issued_on']" />
                <Input
                    v-model="form.prescription.clinic"
                    placeholder="Clinic / hospital"
                />
                <Input
                    v-model="form.prescription.diagnosis"
                    placeholder="Diagnosis"
                />
            </div>

            <div class="flex flex-col gap-3 rounded-xl border bg-card p-4">
                <div class="grid grid-cols-4 gap-2">
                    <button
                        v-for="m in methods"
                        :key="m.value"
                        type="button"
                        :data-on="form.payment_method === m.value"
                        :class="[
                            'flex flex-col items-center gap-1 rounded-lg border py-2.5 text-sm font-medium transition-colors data-[on=true]:text-white',
                            m.tone,
                        ]"
                        @click="form.payment_method = m.value"
                    >
                        <component :is="m.icon" class="size-5" />
                        {{ m.label }}
                    </button>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div class="grid gap-1.5">
                        <Label for="discount">Discount (RM)</Label>
                        <Input
                            id="discount"
                            v-model="form.discount"
                            inputmode="decimal"
                            placeholder="0.00"
                        />
                    </div>
                    <div
                        v-if="form.payment_method === 'cash'"
                        class="grid gap-1.5"
                    >
                        <Label for="tendered">Cash received (RM)</Label>
                        <Input
                            id="tendered"
                            v-model="form.tendered"
                            inputmode="decimal"
                            placeholder="0.00"
                        />
                    </div>
                </div>
                <InputError
                    :message="errors.discount_sen ?? errors.tendered_sen"
                />

                <dl
                    class="tabular grid grid-cols-2 gap-y-1 border-t pt-3 text-sm"
                >
                    <dt class="text-muted-foreground">Subtotal</dt>
                    <dd class="text-right">{{ rm(subtotal) }}</dd>
                    <template v-if="tax"
                        ><dt class="text-muted-foreground">Tax</dt>
                        <dd class="text-right">{{ rm(tax) }}</dd></template
                    >
                    <template v-if="toSen(form.discount)"
                        ><dt class="text-muted-foreground">Discount</dt>
                        <dd class="text-right">
                            −{{ rm(toSen(form.discount)) }}
                        </dd></template
                    >
                    <template
                        v-if="form.payment_method === 'cash' && form.tendered"
                        ><dt class="text-muted-foreground">Change</dt>
                        <dd
                            :class="[
                                'text-right font-semibold',
                                change < 0
                                    ? 'text-rose-600'
                                    : 'text-emerald-600',
                            ]"
                        >
                            {{ rm(change) }}
                        </dd></template
                    >
                </dl>

                <Button
                    class="h-14 rounded-xl bg-violet-600 text-lg hover:bg-violet-700"
                    :disabled="
                        !cart.length ||
                        form.processing ||
                        (hasPoison && !isPharmacist)
                    "
                    @click="charge"
                >
                    Charge {{ rm(total) }}
                </Button>
            </div>
        </aside>
    </div>
</template>
