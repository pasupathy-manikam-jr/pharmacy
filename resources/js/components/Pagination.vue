<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Paginated } from '@/types';

defineProps<{ page: Paginated<unknown> }>();

const sizes = [10, 20, 50, 100];

/** Same page and filters, new page size, back to page 1. */
function resize(value: unknown) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', String(value));
    url.searchParams.delete('page');
    router.get(
        url.pathname + url.search,
        {},
        { preserveState: true, preserveScroll: true },
    );
}
</script>

<template>
    <div
        v-if="page.total > 0"
        class="no-print flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground"
    >
        <div class="flex items-center gap-2">
            <span>Rows per page</span>
            <Select
                :model-value="String(page.per_page)"
                @update:model-value="resize"
            >
                <SelectTrigger
                    size="sm"
                    class="w-20"
                    aria-label="Rows per page"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="n in sizes"
                        :key="n"
                        :value="String(n)"
                        >{{ n }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <span class="ml-2"
                >Page {{ page.current_page }} of {{ page.last_page }},
                {{ page.total }} total</span
            >
        </div>
        <div v-if="page.last_page > 1" class="flex gap-2">
            <Button
                variant="outline"
                size="sm"
                :disabled="!page.prev_page_url"
                as-child
            >
                <Link :href="page.prev_page_url ?? '#'" preserve-scroll
                    >Previous</Link
                >
            </Button>
            <Button
                variant="outline"
                size="sm"
                :disabled="!page.next_page_url"
                as-child
            >
                <Link :href="page.next_page_url ?? '#'" preserve-scroll
                    >Next</Link
                >
            </Button>
        </div>
    </div>
</template>
