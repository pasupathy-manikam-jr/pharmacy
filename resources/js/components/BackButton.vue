<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { Button } from '@/components/ui/button';

defineProps<{
    href: NonNullable<InertiaLinkProps['href']>;
    /** What it goes back to, e.g. "Products". */
    label: string;
}>();
</script>

<template>
    <Button variant="outline" class="no-print" as-child>
        <Link :href="href"><ArrowLeft /> Back to {{ label }}</Link>
    </Button>
</template>
