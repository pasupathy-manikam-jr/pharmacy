<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import ShiftController from '@/actions/App/Http/Controllers/Pharmacy/ShiftController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toSen } from '@/lib/money';

const form = useForm({ float: '' });
const open = () =>
    form
        .transform((d) => ({ opening_float_sen: toSen(d.float) }))
        .post(ShiftController.store.url(), { preserveScroll: true });
</script>

<template>
    <form
        class="flex flex-wrap items-end gap-3"
        novalidate
        @submit.prevent="open"
    >
        <div class="grid gap-1.5">
            <Label for="float">{{ $t('Cash in drawer to start (RM)') }}</Label>
            <Input
                id="float"
                v-model="form.float"
                v-focus
                class="w-48"
                inputmode="decimal"
                placeholder="0.00"
            />
        </div>
        <Button
            :disabled="form.processing"
            class="bg-green-600 hover:bg-green-700"
            >{{ $t('Open shift') }}</Button
        >
        <InputError
            class="w-full"
            :message="(form.errors as Record<string, string>).opening_float_sen"
        />
    </form>
</template>
