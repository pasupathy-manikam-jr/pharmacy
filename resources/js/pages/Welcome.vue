<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';

const page = usePage();

const promises = [
    {
        tone: 'border-violet-500',
        title: 'Sell at the counter',
        text: 'Scan a barcode, take cash, card or e-wallet, print the receipt.',
    },
    {
        tone: 'border-amber-500',
        title: 'Oldest stock leaves first',
        text: 'Every sale picks the batch that expires soonest. Expired batches can’t be sold.',
    },
    {
        tone: 'border-rose-500',
        title: 'Registers fill themselves',
        text: 'Selling a scheduled poison writes the Prescription Book or Poisons Book entry. Entries can’t be edited, only reversed.',
    },
    {
        tone: 'border-sky-500',
        title: 'Ready for MyInvois',
        text: 'Sales carry what LHDN e-invoicing and the 2027 MOH prescription format need.',
    },
];
</script>

<template>
    <Head title="Welcome" />

    <div class="min-h-screen bg-background text-foreground">
        <header
            class="mx-auto flex max-w-6xl items-center justify-between px-4 py-5"
        >
            <div class="flex items-center gap-2.5">
                <div
                    class="flex size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground"
                >
                    <AppLogoIcon class="size-5 fill-current" />
                </div>
                <span class="font-display text-lg font-semibold">{{
                    page.props.name
                }}</span>
            </div>
            <div class="flex items-center gap-2">
                <LanguageSwitcher />
                <Button v-if="page.props.auth.user" as-child>
                    <Link :href="dashboard()">{{ $t('Open dashboard') }}</Link>
                </Button>
                <Button v-else as-child>
                    <Link :href="login()">{{ $t('Log in') }}</Link>
                </Button>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 pt-10 pb-20">
            <section
                class="grid items-center gap-12 lg:grid-cols-[1.1fr_0.9fr]"
            >
                <div class="max-w-xl">
                    <h1
                        class="text-4xl leading-[1.05] font-bold tracking-tight text-balance sm:text-6xl"
                    >
                        {{ $t('The dispensary, kept straight.') }}
                    </h1>
                    <p class="mt-6 text-lg text-muted-foreground">
                        {{
                            $t(
                                'Counter sales, batch-level stock and the statutory poison registers for a Malaysian community pharmacy, in one system your staff can learn in an afternoon.',
                            )
                        }}
                    </p>
                    <div class="mt-8 flex gap-3">
                        <Button size="lg" as-child>
                            <Link
                                :href="
                                    page.props.auth.user ? dashboard() : login()
                                "
                                >{{
                                    page.props.auth.user
                                        ? $t('Open dashboard')
                                        : $t('Log in to your pharmacy')
                                }}</Link
                            >
                        </Button>
                    </div>
                </div>

                <!-- The hero: a dispensing label, the object every pharmacy prints all day. -->
                <figure
                    class="relative mx-auto w-full max-w-sm rotate-[-2deg] rounded-lg bg-white p-5 text-[13px] leading-snug text-neutral-900 shadow-[0_18px_40px_-12px_rgb(15_118_110/0.45)] ring-1 ring-teal-900/10"
                >
                    <div
                        class="-mx-5 -mt-5 mb-4 flex items-center justify-between rounded-t-lg bg-teal-700 px-5 py-2.5 text-white"
                    >
                        <span class="font-display font-semibold">{{
                            $t('Farmasi Seri Mutiara')
                        }}</span>
                        <span class="text-xs text-teal-100">{{
                            $t('Lic. A/12345')
                        }}</span>
                    </div>
                    <div class="flex justify-between text-neutral-500">
                        <span>{{ $t('Rx 004218') }}</span>
                        <span>{{ $t('09 Oct 2026') }}</span>
                    </div>
                    <p class="mt-2 font-semibold">
                        {{ $t('Nur Aisyah binti Ahmad') }}
                    </p>
                    <p
                        class="mt-3 font-display text-xl font-bold text-teal-800"
                    >
                        {{ $t('Amoxicillin 500 mg') }}
                    </p>
                    <p class="text-neutral-600">{{ $t('21 capsules') }}</p>
                    <p
                        class="mt-3 rounded-md bg-violet-50 px-3 py-2 font-medium text-violet-900"
                    >
                        {{
                            $t(
                                'Take 1 capsule three times a day after food. Finish the whole course.',
                            )
                        }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-1.5 text-xs">
                        <span
                            class="rounded-full bg-rose-100 px-2 py-0.5 font-medium text-rose-700"
                            >{{ $t('Group B · Prescription Book #318') }}</span
                        >
                        <span
                            class="rounded-full bg-amber-100 px-2 py-0.5 font-medium text-amber-800"
                            >{{ $t('Batch AMX2207 · exp Mar 2027') }}</span
                        >
                    </div>
                    <p class="mt-3 border-t pt-2 text-xs text-neutral-500">
                        {{
                            $t(
                                'Dispensed by Ph. Lim Wei Ling · Dr. Tan (MMC 45821)',
                            )
                        }}
                    </p>
                </figure>
            </section>

            <section class="mt-24 grid gap-x-10 gap-y-8 sm:grid-cols-2">
                <div
                    v-for="p in promises"
                    :key="p.title"
                    :class="['border-l-4 pl-5', p.tone]"
                >
                    <h2 class="text-lg font-semibold">{{ $t(p.title) }}</h2>
                    <p class="mt-1 max-w-prose text-muted-foreground">
                        {{ $t(p.text) }}
                    </p>
                </div>
            </section>
        </main>
    </div>
</template>
