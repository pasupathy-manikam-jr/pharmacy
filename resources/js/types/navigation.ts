import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    /** Tailwind text colour for the icon, e.g. 'text-violet-400'. */
    color?: string;
    isActive?: boolean;
};

export type NavGroup = {
    label: string;
    items: NavItem[];
};
