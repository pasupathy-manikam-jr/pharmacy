<script setup lang="ts" generic="T extends string | number">
import { Check, ChevronsUpDown } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type { Option } from '@/types';

const model = defineModel<T | null>({ default: null });

const props = defineProps<{
    options: Option<T>[];
    id?: string;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
}>();

const emit = defineEmits<{ select: [value: T] }>();

const open = ref(false);
const selected = computed(() =>
    props.options.find((o) => o.value === model.value),
);

function choose(value: T) {
    model.value = value;
    open.value = false;
    emit('select', value);
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                :id="id"
                type="button"
                variant="outline"
                role="combobox"
                :aria-expanded="open"
                :class="
                    cn(
                        'w-full justify-between font-normal',
                        !selected && 'text-muted-foreground',
                    )
                "
            >
                <span class="truncate">{{
                    selected?.label ?? placeholder ?? $t('Select…')
                }}</span>
                <ChevronsUpDown class="size-4 shrink-0 opacity-50" />
            </Button>
        </PopoverTrigger>
        <PopoverContent
            class="w-(--reka-popover-trigger-width) min-w-64 p-0"
            align="start"
        >
            <Command>
                <CommandInput
                    :placeholder="searchPlaceholder ?? $t('Search…')"
                />
                <CommandList>
                    <CommandEmpty>{{
                        emptyText ?? $t('No results.')
                    }}</CommandEmpty>
                    <CommandGroup>
                        <CommandItem
                            v-for="o in options"
                            :key="o.value"
                            :value="o.value"
                            @select="choose(o.value)"
                        >
                            <Check
                                :class="
                                    cn(
                                        'size-4 text-primary',
                                        o.value === model
                                            ? 'opacity-100'
                                            : 'opacity-0',
                                    )
                                "
                            />
                            <span class="truncate">{{ $t(o.label) }}</span>
                            <span
                                v-if="o.hint"
                                class="ml-auto text-xs text-muted-foreground"
                                >{{ o.hint }}</span
                            >
                        </CommandItem>
                    </CommandGroup>
                </CommandList>
            </Command>
        </PopoverContent>
    </Popover>
</template>
