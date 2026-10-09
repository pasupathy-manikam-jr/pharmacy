<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const branch = computed(() => page.props.auth.branch);
const role = computed(() => page.props.auth.roles[0]);
</script>

<template>
    <footer
        class="no-print sticky bottom-0 z-20 mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-sidebar-border/70 bg-background/95 px-4 py-2 text-xs text-muted-foreground backdrop-blur md:px-6"
    >
        <span v-if="branch">
            <span class="font-medium text-foreground">{{ branch.name }}</span>
            <template v-if="branch.licence_no">{{
                $t(', licence :no', { no: branch.licence_no })
            }}</template>
        </span>
        <span>
            {{ $t('Signed in as') }}
            <span class="capitalize">{{ $t(role) }}</span
            >. {{ page.props.name }} © {{ new Date().getFullYear() }}
        </span>
    </footer>
</template>
