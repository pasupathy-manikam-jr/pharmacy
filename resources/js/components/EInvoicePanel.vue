<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ExternalLink, FileCheck2, RefreshCw, Send } from '@lucide/vue';
import EInvoiceController from '@/actions/App/Http/Controllers/Pharmacy/EInvoiceController';
import { Button } from '@/components/ui/button';

export type EInvoiceSummary = {
    id: number;
    status:
        | 'pending'
        | 'submitted'
        | 'valid'
        | 'invalid'
        | 'cancelled'
        | 'failed';
    environment: string;
    uuid: string | null;
    validation_url: string | null;
    can_cancel: boolean;
    errors: string[];
};

const props = defineProps<{
    einvoice: EInvoiceSummary | null;
    /** POST target that submits (or resubmits) this document. */
    submitUrl?: string;
    submitLabel?: string;
}>();

const tones: Record<EInvoiceSummary['status'], string> = {
    pending:
        'bg-slate-100 text-slate-700 dark:bg-slate-500/20 dark:text-slate-300',
    submitted: 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
    valid: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300',
    invalid: 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
    cancelled:
        'bg-neutral-200 text-neutral-700 dark:bg-neutral-500/20 dark:text-neutral-300',
    failed: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
};

const canSubmit = () =>
    props.submitUrl &&
    (!props.einvoice || ['invalid', 'failed'].includes(props.einvoice.status));

const submit = () =>
    props.submitUrl &&
    router.post(props.submitUrl, {}, { preserveScroll: true });
const poll = () =>
    props.einvoice &&
    router.post(
        EInvoiceController.poll.url(props.einvoice.id),
        {},
        { preserveScroll: true },
    );
</script>

<template>
    <div class="flex flex-wrap items-center gap-2 text-sm">
        <FileCheck2 class="size-4 text-sky-600" />
        <span v-if="!einvoice" class="text-muted-foreground">No e-invoice</span>
        <template v-else>
            <span
                :class="[
                    'rounded-full px-2 py-0.5 font-medium capitalize',
                    tones[einvoice.status],
                ]"
                >{{ einvoice.status }}</span
            >
            <span
                v-if="einvoice.environment === 'sandbox'"
                class="text-xs text-muted-foreground"
                >sandbox</span
            >
            <a
                v-if="einvoice.validation_url"
                :href="einvoice.validation_url"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-1 text-sky-700 hover:underline dark:text-sky-300"
            >
                Validation link <ExternalLink class="size-3.5" />
            </a>
            <Button
                v-if="einvoice.status === 'submitted'"
                size="sm"
                variant="ghost"
                @click="poll"
                ><RefreshCw /> Check status</Button
            >
        </template>
        <Button v-if="canSubmit()" size="sm" variant="outline" @click="submit"
            ><Send /> {{ submitLabel ?? 'Send to LHDN' }}</Button
        >
        <ul
            v-if="einvoice?.errors.length"
            class="w-full list-disc pl-6 text-rose-700 dark:text-rose-300"
        >
            <li v-for="e in einvoice.errors" :key="e">{{ e }}</li>
        </ul>
    </div>
</template>
