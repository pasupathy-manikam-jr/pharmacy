<script setup lang="ts">
import type { DateValue } from '@internationalized/date';
import { parseDate } from '@internationalized/date';
import { CalendarIcon } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { formatDate } from '@/lib/money';
import { cn } from '@/lib/utils';

/** v-model is an ISO date string (YYYY-MM-DD) or null. */
const model = defineModel<string | null>({ default: null });

const props = defineProps<{
    id?: string;
    placeholder?: string;
    class?: string;
}>();

const open = ref(false);

const value = computed({
    get: () => (model.value ? parseDate(model.value) : undefined),
    set: (v: DateValue | undefined) => {
        model.value = v ? v.toString() : null;
        open.value = false;
    },
});
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                :id="id"
                type="button"
                variant="outline"
                :class="
                    cn(
                        'w-full justify-start font-normal',
                        !model && 'text-muted-foreground',
                        props.class,
                    )
                "
            >
                <CalendarIcon class="size-4 text-primary" />
                {{ model ? formatDate(model) : (placeholder ?? 'Pick a date') }}
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-auto p-0" align="start">
            <Calendar
                v-model="value"
                layout="month-and-year"
                :default-placeholder="value"
                initial-focus
            />
        </PopoverContent>
    </Popover>
</template>
