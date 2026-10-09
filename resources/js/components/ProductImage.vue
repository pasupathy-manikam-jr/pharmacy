<script setup lang="ts">
import { Pill } from '@lucide/vue';

defineProps<{
    src: string | null | undefined;
    alt: string;
    /** Tailwind size/shape classes for the frame, e.g. 'size-10 rounded-md'. */
    class?: string;
}>();
</script>

<template>
    <div
        :class="[
            'flex shrink-0 items-center justify-center overflow-hidden bg-indigo-50 text-indigo-400 dark:bg-indigo-500/10',
            $props.class,
        ]"
    >
        <img
            v-if="src"
            :src="src"
            :alt="alt"
            loading="lazy"
            class="size-full object-contain"
        />
        <Pill v-else class="size-1/2 max-h-10 max-w-10" aria-hidden="true" />
    </div>
</template>
