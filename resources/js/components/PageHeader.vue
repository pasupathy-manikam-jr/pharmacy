<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';

defineProps<{
    title: string;
    description?: string;
    icon: LucideIcon;
    /** Tailwind classes for the icon tile, e.g. 'bg-violet-100 text-violet-700'. */
    tone: string;
}>();
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div
                :class="[
                    'flex size-11 items-center justify-center rounded-xl',
                    tone,
                ]"
            >
                <component :is="icon" class="size-6" />
            </div>
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ title }}
                </h1>
                <p v-if="description" class="text-sm text-muted-foreground">
                    {{ description }}
                </p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <slot />
        </div>
    </div>
</template>
