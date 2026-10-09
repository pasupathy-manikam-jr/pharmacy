<script setup lang="ts">
import type { PoisonGroup } from '@/types';

defineProps<{ group: PoisonGroup }>();

const tones: Record<PoisonGroup, string> = {
    none: '',
    B: 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
    C: 'bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-300',
    D: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
    psychotropic:
        'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-500/20 dark:text-fuchsia-300',
    dda: 'bg-red-600 text-white',
};

const labels: Record<PoisonGroup, string> = {
    none: '',
    B: 'Group B',
    C: 'Group C',
    D: 'Group D',
    psychotropic: 'Psychotropic',
    dda: 'DDA',
};
</script>

<template>
    <span
        v-if="group !== 'none'"
        :class="[
            'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
            tones[group],
        ]"
        >{{ labels[group] }}</span
    >
</template>
