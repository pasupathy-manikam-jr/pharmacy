<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    BookLock,
    Boxes,
    Building2,
    ClipboardList,
    FileCheck2,
    FileText,
    History,
    LayoutGrid,
    Pill,
    ReceiptText,
    ScanBarcode,
    ShoppingCart,
    Truck,
    UserCog,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import BranchSwitcher from '@/components/BranchSwitcher.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as audit } from '@/routes/audit';
import { index as branches } from '@/routes/branches';
import { index as customers } from '@/routes/customers';
import { index as einvoice } from '@/routes/einvoice';
import { index as prescriptions } from '@/routes/prescriptions';
import { index as purchaseOrders } from '@/routes/purchase-orders';
import { index as reports } from '@/routes/reports';
import { index as shifts } from '@/routes/shifts';
import { index as pos } from '@/routes/pos';
import { index as products } from '@/routes/products';
import { index as receipts } from '@/routes/receipts';
import { index as register } from '@/routes/register';
import { index as sales } from '@/routes/sales';
import { index as stock } from '@/routes/stock';
import { index as suppliers } from '@/routes/suppliers';
import { index as users } from '@/routes/users';
import type { NavGroup, Role } from '@/types';

const page = usePage();
const can = (...roles: Role[]) =>
    page.props.auth.roles.some((r) => roles.includes(r));

const staff = () => can('owner', 'pharmacist', 'assistant');
const pharmacist = () => can('owner', 'pharmacist');
const owner = () => can('owner');

const groups = computed<NavGroup[]>(() =>
    [
        {
            label: 'Counter',
            items: [
                {
                    title: 'Dashboard',
                    href: dashboard(),
                    icon: LayoutGrid,
                    color: 'text-emerald-300',
                },
                {
                    title: 'Point of sale',
                    href: pos(),
                    icon: ScanBarcode,
                    color: 'text-violet-300',
                },
                {
                    title: 'My shift',
                    href: shifts(),
                    icon: Wallet,
                    color: 'text-green-300',
                },
                {
                    title: 'Sales',
                    href: sales(),
                    icon: ReceiptText,
                    color: 'text-sky-300',
                },
                {
                    title: 'Customers',
                    href: customers(),
                    icon: Users,
                    color: 'text-pink-300',
                },
                ...(staff()
                    ? [
                          {
                              title: 'Prescriptions',
                              href: prescriptions(),
                              icon: FileText,
                              color: 'text-fuchsia-300',
                          },
                      ]
                    : []),
            ],
        },
        {
            label: 'Inventory',
            items: [
                {
                    title: 'Stock',
                    href: stock(),
                    icon: Boxes,
                    color: 'text-amber-300',
                },
                ...(staff()
                    ? [
                          {
                              title: 'Products',
                              href: products(),
                              icon: Pill,
                              color: 'text-indigo-300',
                          },
                          {
                              title: 'Purchase orders',
                              href: purchaseOrders(),
                              icon: ShoppingCart,
                              color: 'text-blue-300',
                          },
                          {
                              title: 'Goods received',
                              href: receipts(),
                              icon: ClipboardList,
                              color: 'text-lime-300',
                          },
                          {
                              title: 'Suppliers',
                              href: suppliers(),
                              icon: Truck,
                              color: 'text-orange-300',
                          },
                      ]
                    : []),
            ],
        },
        {
            label: 'Compliance',
            items: [
                ...(pharmacist()
                    ? [
                          {
                              title: 'Poison registers',
                              href: register(),
                              icon: BookLock,
                              color: 'text-rose-300',
                          },
                      ]
                    : []),
                ...(owner()
                    ? [
                          {
                              title: 'E-invoices',
                              href: einvoice(),
                              icon: FileCheck2,
                              color: 'text-sky-300',
                          },
                      ]
                    : []),
            ],
        },
        {
            label: 'Insights',
            items: [
                ...(pharmacist()
                    ? [
                          {
                              title: 'Reports',
                              href: reports(),
                              icon: BarChart3,
                              color: 'text-yellow-300',
                          },
                      ]
                    : []),
                ...(owner()
                    ? [
                          {
                              title: 'Audit log',
                              href: audit(),
                              icon: History,
                              color: 'text-slate-300',
                          },
                      ]
                    : []),
            ],
        },
        {
            label: 'Admin',
            items: owner()
                ? [
                      {
                          title: 'Staff',
                          href: users(),
                          icon: UserCog,
                          color: 'text-cyan-300',
                      },
                      {
                          title: 'Branches',
                          href: branches(),
                          icon: Building2,
                          color: 'text-teal-300',
                      },
                  ]
                : [],
        },
    ].filter((g) => g.items.length > 0),
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <div class="px-2 pt-1">
                <BranchSwitcher />
            </div>
            <NavMain
                v-for="group in groups"
                :key="group.label"
                :label="group.label"
                :items="group.items"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
