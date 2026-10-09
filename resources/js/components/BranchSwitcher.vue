<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Building2, Check, ChevronsUpDown } from '@lucide/vue';
import { computed } from 'vue';
import BranchController from '@/actions/App/Http/Controllers/Pharmacy/BranchController';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';

const page = usePage();
const current = computed(() => page.props.auth.branch);
const branches = computed(() => page.props.auth.branches);

const go = (id: number) =>
    router.post(BranchController.switch.url(id), {}, { preserveScroll: true });
</script>

<template>
    <SidebarMenu v-if="current">
        <SidebarMenuItem>
            <DropdownMenu v-if="branches.length > 1">
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton :tooltip="current.name">
                        <Building2 class="text-teal-300" />
                        <span class="truncate">{{ current.name }}</span>
                        <ChevronsUpDown class="ml-auto opacity-60" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" class="w-60">
                    <DropdownMenuLabel>Switch branch</DropdownMenuLabel>
                    <DropdownMenuItem
                        v-for="b in branches"
                        :key="b.id"
                        @select="b.id !== current.id && go(b.id)"
                    >
                        <Check
                            :class="[
                                'size-4',
                                b.id === current.id
                                    ? 'opacity-100'
                                    : 'opacity-0',
                            ]"
                        />
                        {{ b.name }}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <SidebarMenuButton
                v-else
                :tooltip="current.name"
                class="cursor-default"
            >
                <Building2 class="text-teal-300" />
                <span class="truncate">{{ current.name }}</span>
            </SidebarMenuButton>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
