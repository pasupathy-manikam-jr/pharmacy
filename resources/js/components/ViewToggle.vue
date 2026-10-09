<script setup lang="ts">
import { LayoutGrid, List } from '@lucide/vue';
import type { ViewMode } from '@/composables/useViewMode';

const mode = defineModel<ViewMode>({ required: true });

const options = [
    { value: 'list', label: 'List view', icon: List },
    { value: 'grid', label: 'Grid view', icon: LayoutGrid },
] as const;
</script>

<template>
    <div
        class="flex rounded-lg border bg-card p-0.5"
        role="group"
        aria-label="Layout"
    >
        <button
            v-for="o in options"
            :key="o.value"
            type="button"
            :aria-label="o.label"
            :aria-pressed="mode === o.value"
            :title="o.label"
            :class="[
                'rounded-md p-1.5 transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                mode === o.value
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:text-foreground',
            ]"
            @click="mode = o.value"
        >
            <component :is="o.icon" class="size-4" />
        </button>
    </div>
</template>
