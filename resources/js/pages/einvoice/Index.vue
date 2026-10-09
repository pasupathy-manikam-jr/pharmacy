<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { FileCheck2, KeyRound, Send } from '@lucide/vue';
import EInvoiceController from '@/actions/App/Http/Controllers/Pharmacy/EInvoiceController';
import type { EInvoiceSummary } from '@/components/EInvoicePanel.vue';
import EInvoicePanel from '@/components/EInvoicePanel.vue';
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
import { Textarea } from '@/components/ui/textarea';
import { intlLocale } from '@/lib/i18n';
import { formatDateTime, rm } from '@/lib/money';
import { index as branches } from '@/routes/branches';
import { index } from '@/routes/einvoice';

defineOptions({
    layout: { breadcrumbs: [{ title: 'E-invoices', href: index() }] },
});

type Month = {
    period: string;
    batch: { id: number; sale_count: number; total_sen: number } | null;
    einvoice: EInvoiceSummary | null;
    pending_sales: number | null;
};

defineProps<{
    branch: {
        id: number;
        name: string;
        tin: string | null;
        brn: string | null;
        company_name: string | null;
    };
    settings: {
        id: number;
        environment: string;
        client_id: string;
        unsigned: boolean;
        active: boolean;
    }[];
    months: Month[];
    thisMonth: { period: string; sales: number };
    recent: {
        id: number;
        number: string;
        type: string;
        status: string;
        environment: string;
        uuid: string | null;
        created_at: string;
    }[];
}>();

const monthLabel = (p: string) =>
    new Date(`${p}-01T00:00:00`).toLocaleDateString(intlLocale(), {
        month: 'long',
        year: 'numeric',
    });
const consolidate = (period: string) =>
    router.post(
        EInvoiceController.consolidate.url(),
        { period },
        { preserveScroll: true },
    );

const form = useForm({
    environment: 'sandbox',
    client_id: '',
    client_secret: '',
    unsigned: false,
    certificate: '',
    private_key: '',
});
const save = () =>
    form.post(EInvoiceController.saveSettings.url(), {
        preserveScroll: true,
        onSuccess: () =>
            form.reset('client_secret', 'certificate', 'private_key'),
    });
const typeLabel: Record<string, string> = {
    '01': 'Invoice',
    '02': 'Credit note',
    '04': 'Refund note',
};
</script>

<template>
    <Head title="E-invoices" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <PageHeader
            :title="$t('E-invoices')"
            :description="
                $t(
                    'LHDN MyInvois. Walk-in sales go out once a month as one consolidated e-invoice.',
                )
            "
            :icon="FileCheck2"
            tone="bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300"
        />

        <p v-if="!branch.tin" class="rounded-xl bg-amber-500 p-4 text-white">
            {{ $t(':branch has no TIN yet.', { branch: branch.name }) }}
            <Link :href="branches()" class="font-semibold underline">{{
                $t('Add it under Branches')
            }}</Link>
            {{ $t('to send e-invoices.') }}
        </p>

        <div class="grid gap-5 xl:grid-cols-[1fr_24rem]">
            <section class="rounded-xl border bg-card">
                <h2 class="border-b p-4 font-semibold">
                    {{ $t('Monthly consolidated e-invoices') }}
                </h2>
                <div
                    v-for="m in months"
                    :key="m.period"
                    class="flex flex-wrap items-center gap-3 border-b px-4 py-3 last:border-b-0"
                >
                    <span class="w-40 font-medium">{{
                        monthLabel(m.period)
                    }}</span>
                    <template v-if="m.batch">
                        <span class="text-sm text-muted-foreground">{{
                            $t(':count sales, :total', {
                                count: m.batch.sale_count,
                                total: rm(m.batch.total_sen),
                            })
                        }}</span>
                        <div class="ml-auto">
                            <EInvoicePanel :einvoice="m.einvoice" />
                        </div>
                        <Button
                            v-if="
                                !m.einvoice ||
                                ['invalid', 'failed'].includes(
                                    m.einvoice.status,
                                )
                            "
                            size="sm"
                            variant="outline"
                            :disabled="!branch.tin"
                            @click="consolidate(m.period)"
                            ><Send /> {{ $t('Resend') }}</Button
                        >
                    </template>
                    <template v-else>
                        <span class="text-sm text-muted-foreground">{{
                            $t(':count walk-in sales', {
                                count: m.pending_sales ?? 0,
                            })
                        }}</span>
                        <Button
                            v-if="m.pending_sales"
                            size="sm"
                            class="ml-auto bg-sky-600 hover:bg-sky-700"
                            :disabled="!branch.tin || !settings.length"
                            @click="consolidate(m.period)"
                            ><Send /> {{ $t('Send consolidated') }}</Button
                        >
                    </template>
                </div>
            </section>

            <form
                class="flex flex-col gap-3 rounded-xl border bg-card p-4"
                novalidate
                @submit.prevent="save"
            >
                <h2 class="flex items-center gap-2 font-semibold">
                    <KeyRound class="size-4 text-sky-600" />
                    {{ $t('MyInvois credentials') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{
                        $t('For TIN :tin.', {
                            tin: branch.tin ?? $t('not set'),
                        })
                    }}
                    <template v-for="s in settings" :key="s.id"
                        ><br />{{
                            $t(':environment: client :client', {
                                environment: $t(s.environment),
                                client: s.client_id,
                            })
                        }}{{ s.active ? ` ${$t('(in use)')}` : '' }}</template
                    >
                </p>
                <Select v-model="form.environment">
                    <SelectTrigger class="w-full"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="sandbox">{{
                            $t('Sandbox (testing)')
                        }}</SelectItem>
                        <SelectItem value="production">{{
                            $t('Production (live)')
                        }}</SelectItem>
                    </SelectContent>
                </Select>
                <Input
                    v-model="form.client_id"
                    :placeholder="$t('Client ID')"
                    autocomplete="off"
                />
                <InputError :message="form.errors.client_id" />
                <Input
                    v-model="form.client_secret"
                    type="password"
                    :placeholder="$t('Client secret (blank keeps saved)')"
                    autocomplete="new-password"
                />
                <Textarea
                    v-model="form.certificate"
                    rows="3"
                    :placeholder="$t('Certificate PEM (blank keeps saved)')"
                />
                <Textarea
                    v-model="form.private_key"
                    rows="3"
                    :placeholder="$t('Private key PEM (blank keeps saved)')"
                />
                <div
                    v-if="form.environment === 'sandbox'"
                    class="flex items-center gap-2"
                >
                    <Checkbox id="unsigned" v-model="form.unsigned" />
                    <Label for="unsigned" class="font-normal">{{
                        $t('Send unsigned (sandbox, no certificate yet)')
                    }}</Label>
                </div>
                <Button
                    :disabled="form.processing || !branch.tin"
                    class="bg-sky-600 hover:bg-sky-700"
                    >{{ $t('Save and use these') }}</Button
                >
            </form>
        </div>

        <section class="rounded-xl border bg-card p-4">
            <h2 class="mb-2 font-semibold">{{ $t('Recent e-invoices') }}</h2>
            <p v-if="!recent.length" class="text-sm text-muted-foreground">
                {{ $t('Nothing sent yet.') }}
            </p>
            <div
                v-for="d in recent"
                :key="d.id"
                class="flex flex-wrap justify-between gap-2 border-t py-2 text-sm first:border-t-0"
            >
                <span class="font-medium">{{ d.number }}</span>
                <span>{{ typeLabel[d.type] ?? d.type }}</span>
                <span class="capitalize"
                    >{{ $t(d.status) }}
                    <span class="text-muted-foreground">{{
                        d.environment
                    }}</span></span
                >
                <span class="text-muted-foreground">{{
                    formatDateTime(d.created_at)
                }}</span>
            </div>
        </section>
    </div>
</template>
