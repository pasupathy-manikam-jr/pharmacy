<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { HandCoins, UserRound } from '@lucide/vue';
import CustomerController from '@/actions/App/Http/Controllers/Pharmacy/CustomerController';
import DatePicker from '@/components/DatePicker.vue';
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
import { formatDate, formatDateTime, rm, toSen } from '@/lib/money';
import { index } from '@/routes/customers';
import { show as saleShow } from '@/routes/sales';
import type { Customer } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Customers', href: index() },
            { title: 'Customer', href: '#' },
        ],
    },
});

type FullCustomer = Customer & {
    tin: string | null;
    brn: string | null;
    email: string | null;
};

const props = defineProps<{
    customer: FullCustomer;
    balance_sen: number;
    sales: {
        id: number;
        number: string;
        created_at: string;
        total_sen: number;
        payment_method: string;
        status: string;
    }[];
    payments: {
        id: number;
        created_at: string;
        amount_sen: number;
        method: string;
        reference: string | null;
        user: { name: string };
    }[];
    prescriptions: {
        id: number;
        prescriber_name: string;
        prescriber_reg_no: string | null;
        clinic: string | null;
        issued_on: string;
        refills_allowed: number;
        sales_count: number;
    }[];
}>();

const c = props.customer;
const form = useForm({
    name: c.name,
    ic_no: c.ic_no ?? '',
    dob: c.dob,
    sex: c.sex,
    phone: c.phone ?? '',
    email: c.email ?? '',
    address: c.address ?? '',
    citizenship: c.citizenship ?? '',
    allergies: c.allergies ?? '',
    tin: c.tin ?? '',
    brn: c.brn ?? '',
});
const save = () =>
    form.put(CustomerController.update.url(c.id), { preserveScroll: true });

const pay = useForm({ amount: '', method: 'cash', reference: '' });
const receive = () =>
    pay
        .transform((d) => ({
            amount_sen: toSen(d.amount),
            method: d.method,
            reference: d.reference || null,
        }))
        .post(CustomerController.payment.url(c.id), {
            preserveScroll: true,
            onSuccess: () => pay.reset(),
        });

const methodLabel: Record<string, string> = {
    cash: 'Cash',
    card: 'Card',
    ewallet: 'E-wallet',
    credit: 'On account',
    bank: 'Bank transfer',
};
</script>

<template>
    <Head :title="customer.name" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <PageHeader
            :back="{ href: index(), label: 'customers' }"
            :title="customer.name"
            :description="customer.ic_no ?? 'No MyKad recorded'"
            :icon="UserRound"
            tone="bg-pink-100 text-pink-700 dark:bg-pink-500/20 dark:text-pink-300"
        >
            <span
                v-if="customer.allergies"
                class="rounded-lg bg-rose-600 px-3 py-1.5 text-sm font-medium text-white"
                >Allergic to {{ customer.allergies }}</span
            >
        </PageHeader>

        <div class="grid gap-5 xl:grid-cols-[1fr_22rem]">
            <form
                class="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-3"
                novalidate
                @submit.prevent="save"
            >
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label for="name">Full name</Label>
                    <Input id="name" v-model="form.name" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="ic">MyKad / passport</Label>
                    <Input id="ic" v-model="form.ic_no" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="dob">Date of birth</Label>
                    <DatePicker
                        id="dob"
                        v-model="form.dob"
                        placeholder="Select date"
                    />
                    <InputError :message="form.errors.dob" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="sex">Sex</Label>
                    <Select v-model="form.sex">
                        <SelectTrigger id="sex" class="w-full"
                            ><SelectValue placeholder="Select"
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="F">Female</SelectItem>
                            <SelectItem value="M">Male</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="citizenship">Citizenship</Label>
                    <Input id="citizenship" v-model="form.citizenship" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="phone">Phone</Label>
                    <Input id="phone" v-model="form.phone" />
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label for="email">Email</Label>
                    <Input id="email" v-model="form.email" />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-1.5 sm:col-span-3">
                    <Label for="address">Address</Label>
                    <Input id="address" v-model="form.address" />
                </div>
                <div class="grid gap-1.5 sm:col-span-3">
                    <Label for="allergies">Drug allergies</Label>
                    <Input
                        id="allergies"
                        v-model="form.allergies"
                        placeholder="Comma separated, e.g. Penicillin, Aspirin"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="tin">TIN (for their own e-invoice)</Label>
                    <Input
                        id="tin"
                        v-model="form.tin"
                        placeholder="IG12345678901"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="brn">Business reg. no.</Label>
                    <Input id="brn" v-model="form.brn" />
                </div>
                <div class="flex items-end">
                    <Button
                        :disabled="form.processing"
                        class="bg-pink-600 hover:bg-pink-700"
                        >Save customer</Button
                    >
                </div>
            </form>

            <aside class="flex flex-col gap-4">
                <div
                    :class="[
                        'rounded-xl p-5',
                        balance_sen > 0
                            ? 'bg-amber-500 text-white'
                            : 'border bg-card',
                    ]"
                >
                    <p
                        :class="
                            balance_sen > 0
                                ? 'text-amber-50'
                                : 'text-muted-foreground'
                        "
                    >
                        Owes on account
                    </p>
                    <p class="tabular font-display text-4xl font-bold">
                        {{ rm(balance_sen) }}
                    </p>
                </div>
                <form
                    v-if="balance_sen > 0"
                    class="grid gap-3 rounded-xl border bg-card p-4"
                    novalidate
                    @submit.prevent="receive"
                >
                    <h2 class="flex items-center gap-2 font-semibold">
                        <HandCoins class="size-4 text-amber-600" /> Receive
                        payment
                    </h2>
                    <Input
                        v-model="pay.amount"
                        inputmode="decimal"
                        placeholder="Amount (RM)"
                    />
                    <InputError
                        :message="
                            (pay.errors as Record<string, string>).amount_sen
                        "
                    />
                    <Select v-model="pay.method">
                        <SelectTrigger class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="m in ['cash', 'card', 'ewallet', 'bank']"
                                :key="m"
                                :value="m"
                                >{{ methodLabel[m] }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="pay.errors.method" />
                    <Input
                        v-model="pay.reference"
                        placeholder="Reference (optional)"
                    />
                    <Button
                        :disabled="pay.processing"
                        class="bg-amber-600 hover:bg-amber-700"
                        >Record payment</Button
                    >
                </form>
            </aside>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="rounded-xl border bg-card p-4">
                <h2 class="mb-2 font-semibold">Purchases</h2>
                <p v-if="!sales.length" class="text-sm text-muted-foreground">
                    No purchases yet.
                </p>
                <div
                    v-for="s in sales"
                    :key="s.id"
                    class="flex justify-between border-t py-2 text-sm first:border-t-0"
                >
                    <Link
                        :href="saleShow(s.id)"
                        class="text-sky-700 hover:underline dark:text-sky-300"
                        >{{ s.number }}</Link
                    >
                    <span class="text-muted-foreground">{{
                        formatDateTime(s.created_at)
                    }}</span>
                    <span>{{ methodLabel[s.payment_method] }}</span>
                    <span class="tabular font-semibold">{{
                        rm(s.total_sen)
                    }}</span>
                </div>
                <template v-if="payments.length">
                    <h3 class="mt-4 mb-1 font-semibold">Payments received</h3>
                    <div
                        v-for="p in payments"
                        :key="p.id"
                        class="flex justify-between border-t py-2 text-sm"
                    >
                        <span>{{ formatDateTime(p.created_at) }}</span>
                        <span
                            >{{ methodLabel[p.method] }} {{ p.reference }}</span
                        >
                        <span
                            class="tabular font-semibold text-emerald-700 dark:text-emerald-300"
                            >{{ rm(p.amount_sen) }}</span
                        >
                    </div>
                </template>
            </section>
            <section class="rounded-xl border bg-card p-4">
                <h2 class="mb-2 font-semibold">Prescriptions</h2>
                <p
                    v-if="!prescriptions.length"
                    class="text-sm text-muted-foreground"
                >
                    No prescriptions recorded.
                </p>
                <div
                    v-for="p in prescriptions"
                    :key="p.id"
                    class="flex items-center justify-between border-t py-2 text-sm first:border-t-0"
                >
                    <div>
                        <p class="font-medium">
                            {{ p.prescriber_name }} {{ p.prescriber_reg_no }}
                        </p>
                        <p class="text-muted-foreground">
                            {{ p.clinic }} {{ formatDate(p.issued_on) }}
                        </p>
                    </div>
                    <span
                        :class="[
                            'rounded-full px-2 py-0.5 font-medium',
                            p.sales_count >= 1 + p.refills_allowed
                                ? 'bg-neutral-200 text-neutral-700 dark:bg-neutral-500/20 dark:text-neutral-300'
                                : 'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-500/20 dark:text-fuchsia-300',
                        ]"
                    >
                        Dispensed {{ p.sales_count }} of
                        {{ 1 + p.refills_allowed }}
                    </span>
                </div>
            </section>
        </div>
    </div>
</template>
