import { useLocalStorage } from '@vueuse/core';

export type ViewMode = 'list' | 'grid';

/** Remembers each page's list/grid choice in this browser only. */
export const useViewMode = (page: string, initial: ViewMode = 'list') =>
    useLocalStorage<ViewMode>(`view-mode:${page}`, initial);
