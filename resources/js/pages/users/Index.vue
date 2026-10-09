<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { KeyRound, UserCog } from '@lucide/vue';
import { ref } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Pharmacy/UserController';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index } from '@/routes/users';
import type { Role } from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Staff', href: index() }] },
});

type StaffUser = { id: number; name: string; email: string; role: Role | null };

defineProps<{ users: StaffUser[]; roles: Role[] }>();

const me = usePage().props.auth.user.id;

const roleInfo: Record<Role, { label: string; text: string; tone: string }> = {
    owner: {
        label: 'Owner',
        text: 'Everything, including staff accounts.',
        tone: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-500/20 dark:text-cyan-300',
    },
    pharmacist: {
        label: 'Pharmacist',
        text: 'Sells poisons, refunds, sees registers.',
        tone: 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
    },
    assistant: {
        label: 'Assistant',
        text: 'Sells, manages products and deliveries.',
        tone: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
    },
    cashier: {
        label: 'Cashier',
        text: 'Sells non-poison items only.',
        tone: 'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300',
    },
};

const form = useForm({
    name: '',
    email: '',
    password: '',
    role: 'cashier' as Role,
});
const submit = () =>
    form.post(UserController.store.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });

const editing = ref<StaffUser | null>(null);
const edit = useForm({ role: 'cashier' as Role, password: '' });

function openEdit(u: StaffUser) {
    editing.value = u;
    edit.clearErrors();
    edit.role = u.role ?? 'cashier';
    edit.password = '';
}

function saveEdit() {
    if (!editing.value) return;
    edit.transform((d) => ({ role: d.role, password: d.password || null })).put(
        UserController.update.url(editing.value.id),
        { preserveScroll: true, onSuccess: () => (editing.value = null) },
    );
}
</script>

<template>
    <Head title="Staff" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <PageHeader
            :title="$t('Staff')"
            :description="
                $t('Who can log in at this branch, and what they can do.')
            "
            :icon="UserCog"
            tone="bg-cyan-100 text-cyan-700 dark:bg-cyan-500/20 dark:text-cyan-300"
        />

        <form
            class="grid gap-3 rounded-xl border border-cyan-200 bg-cyan-50/50 p-4 sm:grid-cols-5 dark:border-cyan-500/30 dark:bg-cyan-500/5"
            novalidate
            @submit.prevent="submit"
        >
            <div class="grid gap-1.5">
                <Label for="u-name">{{ $t('Name') }}</Label>
                <Input id="u-name" v-model="form.name" />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-1.5">
                <Label for="u-email">{{ $t('Email') }}</Label>
                <Input id="u-email" v-model="form.email" autocomplete="off" />
                <InputError :message="form.errors.email" />
            </div>
            <div class="grid gap-1.5">
                <Label for="u-password">{{ $t('Password') }}</Label>
                <PasswordInput
                    id="u-password"
                    v-model="form.password"
                    autocomplete="new-password"
                />
                <InputError :message="form.errors.password" />
            </div>
            <div class="grid gap-1.5">
                <Label for="u-role">{{ $t('Role') }}</Label>
                <Select v-model="form.role">
                    <SelectTrigger id="u-role" class="w-full"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="r in roles" :key="r" :value="r">{{
                            $t(roleInfo[r].label)
                        }}</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.role" />
            </div>
            <div class="flex items-end">
                <Button
                    :disabled="form.processing"
                    class="w-full bg-cyan-600 hover:bg-cyan-700"
                    >{{ $t('Add staff member') }}</Button
                >
            </div>
            <p class="text-sm text-muted-foreground sm:col-span-5">
                {{ $t(roleInfo[form.role].text) }}
            </p>
        </form>

        <div class="rounded-xl border bg-card">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{{ $t('Name') }}</TableHead>
                        <TableHead>{{ $t('Email') }}</TableHead>
                        <TableHead>{{ $t('Role') }}</TableHead>
                        <TableHead class="col-action" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="u in users" :key="u.id">
                        <TableCell class="font-medium">
                            {{ u.name }}
                            <span
                                v-if="u.id === me"
                                class="text-muted-foreground"
                                >{{ $t('(you)') }}</span
                            >
                        </TableCell>
                        <TableCell>{{ u.email }}</TableCell>
                        <TableCell>
                            <span
                                v-if="u.role"
                                :class="[
                                    'rounded-full px-2 py-0.5 text-sm font-medium',
                                    roleInfo[u.role].tone,
                                ]"
                                >{{ $t(roleInfo[u.role].label) }}</span
                            >
                        </TableCell>
                        <TableCell class="col-action text-right">
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="openEdit(u)"
                                ><KeyRound />
                                {{ $t('Change role or password') }}</Button
                            >
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Dialog
            :open="editing !== null"
            @update:open="(o) => !o && (editing = null)"
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{
                        $t('Update :name', { name: editing?.name ?? '' })
                    }}</DialogTitle>
                    <DialogDescription>{{
                        $t('Leave the password blank to keep the current one.')
                    }}</DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" novalidate @submit.prevent="saveEdit">
                    <div class="grid gap-1.5">
                        <Label for="e-role">{{ $t('Role') }}</Label>
                        <Select v-model="edit.role">
                            <SelectTrigger id="e-role" class="w-full"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="r in roles"
                                    :key="r"
                                    :value="r"
                                    >{{ $t(roleInfo[r].label) }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="edit.errors.role" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="e-password">{{ $t('New password') }}</Label>
                        <PasswordInput
                            id="e-password"
                            v-model="edit.password"
                            autocomplete="new-password"
                        />
                        <InputError :message="edit.errors.password" />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="editing = null"
                            >{{ $t('Cancel') }}</Button
                        >
                        <Button
                            :disabled="edit.processing"
                            class="bg-cyan-600 hover:bg-cyan-700"
                            >{{ $t('Save changes') }}</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
