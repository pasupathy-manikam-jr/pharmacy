<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

defineProps<{ page: Paginated<unknown> }>();
</script>

<template>
    <div
        v-if="page.last_page > 1"
        class="flex items-center justify-between text-sm text-muted-foreground"
    >
        <span
            >Page {{ page.current_page }} of {{ page.last_page }} ·
            {{ page.total }} total</span
        >
        <div class="flex gap-2">
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
