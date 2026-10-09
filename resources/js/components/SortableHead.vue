<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ChevronsUpDown } from '@lucide/vue';
import { computed } from 'vue';
import { TableHead } from '@/components/ui/table';
import type { SortState } from '@/types';

const props = defineProps<{
    name: string;
    sort: SortState;
    align?: 'right';
    /** Sort rows already on the page (emit) instead of asking the server. */
    local?: boolean;
}>();

const emit = defineEmits<{ sort: [name: string] }>();

const active = computed(() => props.sort.sort === props.name);

function toggle() {
    if (props.local) {
        emit('sort', props.name);
        return;
    }
    const url = new URL(window.location.href);
    url.searchParams.set('sort', props.name);
    url.searchParams.set(
        'dir',
        active.value && props.sort.dir === 'asc' ? 'desc' : 'asc',
    );
    url.searchParams.delete('page');
    router.get(
        url.pathname + url.search,
        {},
        { preserveState: true, preserveScroll: true },
    );
}
</script>

<template>
    <TableHead
        :class="align === 'right' && 'text-right'"
        :aria-sort="
            active ? (sort.dir === 'asc' ? 'ascending' : 'descending') : 'none'
        "
    >
        <button
            type="button"
            :class="[
                'inline-flex items-center gap-1 rounded-sm font-medium hover:text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                align === 'right' && 'flex-row-reverse',
                active && 'text-primary',
            ]"
            @click="toggle"
        >
            <slot />
            <ArrowUp v-if="active && sort.dir === 'asc'" class="size-3.5" />
            <ArrowDown v-else-if="active" class="size-3.5" />
            <ChevronsUpDown v-else class="size-3.5 opacity-40" />
        </button>
    </TableHead>
</template>
