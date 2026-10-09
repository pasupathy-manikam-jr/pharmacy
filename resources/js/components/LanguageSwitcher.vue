<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Check, Languages } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { update } from '@/routes/locale';

const page = usePage();
const current = computed(() => page.props.locale);
const locales = computed(() => page.props.locales);

const choose = (code: string) =>
    code !== current.value &&
    router.post(
        update.url(),
        { locale: code },
        { preserveScroll: true, preserveState: true },
    );
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="sm" :aria-label="$t('Language')">
                <Languages class="text-primary" />
                <span>{{ locales[current] }}</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-44">
            <DropdownMenuItem
                v-for="(name, code) in locales"
                :key="code"
                @select="choose(String(code))"
            >
                <Check
                    :class="[
                        'size-4',
                        code === current ? 'opacity-100' : 'opacity-0',
                    ]"
                />
                <span :lang="String(code)">{{ name }}</span>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
