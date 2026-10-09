const myr = new Intl.NumberFormat('en-MY', {
    style: 'currency',
    currency: 'MYR',
});

/** 1250 → "RM 12.50" */
export const rm = (sen: number): string => myr.format(sen / 100);

/** "12.5" → 1250; blank or invalid → 0 */
export const toSen = (value: string | number | null | undefined): number => {
    const n = Number.parseFloat(String(value ?? ''));
    return Number.isFinite(n) ? Math.round(n * 100) : 0;
};

/** 1250 → "12.50" for editing in an input */
export const fromSen = (sen: number): string => (sen / 100).toFixed(2);

export const formatDate = (iso: string): string =>
    new Date(iso.length === 10 ? `${iso}T00:00:00` : iso).toLocaleDateString(
        'en-MY',
        { day: '2-digit', month: 'short', year: 'numeric' },
    );

export const formatDateTime = (iso: string): string =>
    new Date(iso).toLocaleString('en-MY', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
