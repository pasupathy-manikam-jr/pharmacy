<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 32 32"
        :class="className"
        v-bind="$attrs"
    >
        <path
            fill="currentColor"
            d="M12.5 6h7v6.5H26v7h-6.5V26h-7v-6.5H6v-7h6.5z"
        />
    </svg>
</template>
