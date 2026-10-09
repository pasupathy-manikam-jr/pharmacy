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
        <div class="flex flex-wrap items-center gap-2">
            <span>{{ $t('Rows per page') }}</span>
            <Select
                :model-value="String(page.per_page)"
                @update:model-value="resize"
            >
                <SelectTrigger
                    size="sm"
                    class="w-20"
                    :aria-label="$t('Rows per page')"
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
            <span class="ml-2">{{
                $t('Showing :from to :to of :total results', {
                    from: page.from ?? 0,
                    to: page.to ?? 0,
                    total: page.total,
                })
            }}</span>
        </div>
        <nav
            v-if="page.last_page > 1"
            class="flex flex-wrap gap-1"
            :aria-label="$t('Pages')"
        >
            <Button
                variant="outline"
                size="sm"
                :disabled="!page.prev_page_url"
                as-child
            >
                <Link :href="page.prev_page_url ?? '#'" preserve-scroll>{{
                    $t('Previous')
                }}</Link>
            </Button>
            <template v-for="(link, i) in page.links.slice(1, -1)" :key="i">
                <span v-if="!link.url" class="px-2 py-1">…</span>
                <Button
                    v-else
                    size="sm"
                    :variant="link.active ? 'default' : 'outline'"
                    :aria-current="link.active ? 'page' : undefined"
                    class="min-w-8"
                    as-child
                >
                    <Link :href="link.url" preserve-scroll>{{
                        link.label
                    }}</Link>
                </Button>
            </template>
            <Button
                variant="outline"
                size="sm"
                :disabled="!page.next_page_url"
                as-child
            >
                <Link :href="page.next_page_url ?? '#'" preserve-scroll>{{
                    $t('Next')
                }}</Link>
            </Button>
        </nav>
    </div>
</template>
