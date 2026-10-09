<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Building2, Pencil, Plus } from '@lucide/vue';
import { ref } from 'vue';
import BranchController from '@/actions/App/Http/Controllers/Pharmacy/BranchController';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/branches';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Branches', href: index() }] },
});

type FullBranch = {
    id: number;
    name: string;
    company_name: string | null;
    licence_no: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    postcode: string | null;
    city: string | null;
    state: string | null;
    tin: string | null;
    brn: string | null;
    sst_no: string | null;
    msic_code: string;
    users_count: number;
};

defineProps<{ branches: FullBranch[]; states: Record<string, string> }>();

const current = usePage().props.auth.branch?.id;
const fields = [
    'name',
    'company_name',
    'licence_no',
    'phone',
    'email',
    'address',
    'postcode',
    'city',
    'state',
    'tin',
    'brn',
    'sst_no',
    'msic_code',
] as const;
type Fields = Record<(typeof fields)[number], string>;

const editing = ref<FullBranch | 'new' | null>(null);
const form = useForm<Fields>(
    Object.fromEntries(fields.map((f) => [f, ''])) as Fields,
);

function open(b: FullBranch | 'new') {
    form.clearErrors();
    for (const f of fields)
        form[f] =
            b === 'new' ? (f === 'msic_code' ? '47731' : '') : (b[f] ?? '');
    editing.value = b;
}

function save() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    };
    if (editing.value === 'new') form.post(BranchController.store.url(), opts);
    else if (editing.value)
        form.put(BranchController.update.url(editing.value.id), opts);
}
</script>

<template>
    <Head title="Branches" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <PageHeader
            title="Branches"
            description="Each outlet’s licence, address and the company details printed on receipts and e-invoices."
            :icon="Building2"
            tone="bg-teal-100 text-teal-700 dark:bg-teal-500/20 dark:text-teal-300"
        >
            <Button class="bg-teal-600 hover:bg-teal-700" @click="open('new')"
                ><Plus /> Add branch</Button
            >
        </PageHeader>

        <div class="grid gap-4 md:grid-cols-2">
            <section
                v-for="b in branches"
                :key="b.id"
                :class="[
                    'rounded-xl border p-5',
                    b.id === current
                        ? 'border-teal-400 bg-teal-50/60 dark:border-teal-500/50 dark:bg-teal-500/5'
                        : 'bg-card',
                ]"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">{{ b.name }}</h2>
                        <p class="text-sm text-muted-foreground">
                            {{ b.company_name }}
                            {{ b.licence_no ? `Licence ${b.licence_no}` : '' }}
                        </p>
                    </div>
                    <Button variant="ghost" size="sm" @click="open(b)"
                        ><Pencil /> Edit</Button
                    >
                </div>
                <p class="mt-3 text-sm">
                    {{ b.address }} {{ b.postcode }} {{ b.city }}
                </p>
                <p class="text-sm text-muted-foreground">
                    {{ b.phone }} {{ b.email }}
                </p>
                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    <span
                        :class="[
                            'rounded-full px-2 py-0.5 font-medium',
                            b.tin
                                ? 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300'
                                : 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
                        ]"
                        >{{
                            b.tin
                                ? `TIN ${b.tin}`
                                : 'No TIN: e-invoices disabled'
                        }}</span
                    >
                    <span class="rounded-full bg-muted px-2 py-0.5 font-medium"
                        >{{ b.users_count }} staff</span
                    >
                    <span
                        v-if="b.id === current"
                        class="rounded-full bg-teal-600 px-2 py-0.5 font-medium text-white"
                        >You’re here</span
                    >
                </div>
            </section>
        </div>

        <Dialog
            :open="editing !== null"
            @update:open="(o) => !o && (editing = null)"
        >
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{
                        editing === 'new'
                            ? 'Add branch'
                            : `Edit ${editing?.name}`
                    }}</DialogTitle>
                    <DialogDescription
                        >The TIN, BRN and address are what LHDN sees as the
                        supplier on e-invoices.</DialogDescription
                    >
                </DialogHeader>
                <form
                    class="grid gap-3 sm:grid-cols-2"
                    novalidate
                    @submit.prevent="save"
                >
                    <div class="grid gap-1.5">
                        <Label for="b-name">Branch name</Label
                        ><Input id="b-name" v-model="form.name" /><InputError
                            :message="form.errors.name"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="b-company">Registered company name</Label
                        ><Input id="b-company" v-model="form.company_name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="b-licence">Pharmacy licence no.</Label
                        ><Input id="b-licence" v-model="form.licence_no" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="b-phone">Phone</Label
                        ><Input id="b-phone" v-model="form.phone" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="b-address">Address</Label
                        ><Input id="b-address" v-model="form.address" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5">
                            <Label for="b-postcode">Postcode</Label
                            ><Input id="b-postcode" v-model="form.postcode" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="b-city">City</Label
                            ><Input id="b-city" v-model="form.city" />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="b-state">State</Label>
                        <Select v-model="form.state">
                            <SelectTrigger id="b-state" class="w-full"
                                ><SelectValue placeholder="Choose state"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="(label, code) in states"
                                    :key="code"
                                    :value="String(code)"
                                    >{{ label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="b-email">Email</Label
                        ><Input id="b-email" v-model="form.email" /><InputError
                            :message="form.errors.email"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="b-tin">TIN</Label
                        ><Input
                            id="b-tin"
                            v-model="form.tin"
                            placeholder="C12345678901"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="b-brn">Business reg. no.</Label
                        ><Input id="b-brn" v-model="form.brn" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="b-sst">SST no.</Label
                        ><Input id="b-sst" v-model="form.sst_no" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="b-msic">MSIC code</Label
                        ><Input
                            id="b-msic"
                            v-model="form.msic_code"
                        /><InputError :message="form.errors.msic_code" />
                    </div>
                    <div class="flex justify-end gap-2 sm:col-span-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="editing = null"
                            >Cancel</Button
                        >
                        <Button
                            :disabled="form.processing"
                            class="bg-teal-600 hover:bg-teal-700"
                            >{{
                                editing === 'new' ? 'Add branch' : 'Save branch'
                            }}</Button
                        >
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
