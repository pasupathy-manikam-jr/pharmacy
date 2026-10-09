<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Search, Users } from '@lucide/vue';
import { ref } from 'vue';
import CustomerController from '@/actions/App/Http/Controllers/Pharmacy/CustomerController';
import DatePicker from '@/components/DatePicker.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
import ViewToggle from '@/components/ViewToggle.vue';
import { useInitials } from '@/composables/useInitials';
import { useViewMode } from '@/composables/useViewMode';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/money';
import { index, show } from '@/routes/customers';
import type { Customer, Paginated } from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Customers', href: index() }] },
});

const props = defineProps<{ customers: Paginated<Customer>; search: string }>();

const q = ref(props.search);
const view = useViewMode('customers');
const { getInitials } = useInitials();
const tints = [
    'bg-pink-100 text-pink-700',
    'bg-violet-100 text-violet-700',
    'bg-sky-100 text-sky-700',
    'bg-amber-100 text-amber-800',
    'bg-emerald-100 text-emerald-700',
    'bg-indigo-100 text-indigo-700',
];
const search = () =>
    router.get(
        index().url,
        { search: q.value || undefined },
        { preserveState: true },
    );

const form = useForm({
    name: '',
    ic_no: '',
    dob: null as string | null,
    sex: null as string | null,
    phone: '',
    address: '',
    citizenship: 'Malaysian',
    allergies: '',
});
const submit = () =>
    form.post(CustomerController.store.url(), {
        preserveScroll: true,
    });
</script>

<template>
    <Head title="Customers" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <PageHeader
            title="Customers"
            description="Patients and regulars. Allergies show up at the counter."
            :icon="Users"
            tone="bg-pink-100 text-pink-700 dark:bg-pink-500/20 dark:text-pink-300"
        >
            <form class="relative" novalidate @submit.prevent="search">
                <Search
                    class="absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                />
                <Input
                    v-model="q"
                    class="w-64 pl-8"
                    placeholder="Name, IC or phone"
                />
            </form>
            <ViewToggle v-model="view" />
        </PageHeader>

        <form
            class="grid gap-3 rounded-xl border border-pink-200 bg-pink-50/50 p-4 sm:grid-cols-4 dark:border-pink-500/30 dark:bg-pink-500/5"
            novalidate
            @submit.prevent="submit"
        >
            <div class="grid gap-1.5 sm:col-span-2">
                <Label for="c-name">Full name</Label>
                <Input id="c-name" v-model="form.name" />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-1.5">
                <Label for="c-ic">MyKad / passport</Label>
                <Input id="c-ic" v-model="form.ic_no" />
            </div>
            <div class="grid gap-1.5">
                <Label for="c-phone">Phone</Label>
                <Input id="c-phone" v-model="form.phone" />
            </div>
            <div class="grid gap-1.5">
                <Label for="c-dob">Date of birth</Label>
                <DatePicker
                    id="c-dob"
                    v-model="form.dob"
                    placeholder="Select date"
                />
                <InputError :message="form.errors.dob" />
            </div>
            <div class="grid gap-1.5">
                <Label for="c-sex">Sex</Label>
                <Select v-model="form.sex">
                    <SelectTrigger id="c-sex" class="w-full"
                        ><SelectValue placeholder="Select"
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="F">Female</SelectItem>
                        <SelectItem value="M">Male</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1.5 sm:col-span-2">
                <Label for="c-allergies">Drug allergies</Label>
                <Input
                    id="c-allergies"
                    v-model="form.allergies"
                    placeholder="e.g. Penicillin"
                />
            </div>
            <div class="grid gap-1.5 sm:col-span-3">
                <Label for="c-address">Address</Label>
                <Input id="c-address" v-model="form.address" />
            </div>
            <div class="flex items-end">
                <Button
                    :disabled="form.processing"
                    class="w-full bg-pink-600 hover:bg-pink-700"
                    >Add customer</Button
                >
            </div>
        </form>

        <div v-if="view === 'list'" class="rounded-xl border bg-card">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>MyKad</TableHead>
                        <TableHead>Born</TableHead>
                        <TableHead>Phone</TableHead>
                        <TableHead>Allergies</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!customers.data.length">
                        <TableCell
                            colspan="5"
                            class="py-10 text-center text-muted-foreground"
                            >No customers found.</TableCell
                        >
                    </TableRow>
                    <TableRow v-for="c in customers.data" :key="c.id">
                        <TableCell class="font-medium">
                            <Link
                                :href="show(c.id)"
                                class="text-pink-700 hover:underline dark:text-pink-300"
                                >{{ c.name }}</Link
                            >
                        </TableCell>
                        <TableCell>{{ c.ic_no }}</TableCell>
                        <TableCell>{{
                            c.dob ? formatDate(c.dob) : ''
                        }}</TableCell>
                        <TableCell>{{ c.phone }}</TableCell>
                        <TableCell>
                            <span
                                v-if="c.allergies"
                                class="rounded-md bg-rose-100 px-2 py-0.5 text-sm font-medium text-rose-700 dark:bg-rose-500/20 dark:text-rose-300"
                                >{{ c.allergies }}</span
                            >
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
                v-if="!customers.data.length"
                class="col-span-full rounded-xl border border-dashed p-10 text-center text-muted-foreground"
            >
                No customers found.
            </p>
            <Link
                v-for="c in customers.data"
                :key="c.id"
                :href="show(c.id)"
                class="flex gap-3 rounded-xl border bg-card p-4 transition-colors hover:border-pink-300 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none dark:hover:border-pink-500/50"
            >
                <span
                    :class="[
                        'flex size-11 shrink-0 items-center justify-center rounded-full font-semibold',
                        tints[c.id % tints.length],
                    ]"
                    >{{ getInitials(c.name) }}</span
                >
                <div class="min-w-0 flex-1">
                    <h2 class="truncate font-semibold">{{ c.name }}</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ c.ic_no ?? 'No MyKad' }}
                    </p>
                    <p class="text-sm text-muted-foreground">{{ c.phone }}</p>
                    <span
                        v-if="c.allergies"
                        class="mt-2 inline-block rounded-md bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700 dark:bg-rose-500/20 dark:text-rose-300"
                        >Allergic: {{ c.allergies }}</span
                    >
                </div>
            </Link>
        </div>
        <Pagination :page="customers" />
    </div>
</template>
