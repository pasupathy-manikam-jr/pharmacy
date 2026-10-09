import { intlLocale } from '@/lib/i18n';

const formatters = new Map<string, Intl.NumberFormat>();

/**
 * 1250 → "RM 12.50". Always "RM" (some locales would print "MYR"), digits in the
 * interface language's number style.
 */
export const rm = (sen: number): string => {
    const locale = intlLocale();
    let f = formatters.get(locale);
    if (!f) {
        f = new Intl.NumberFormat(locale, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
        formatters.set(locale, f);
    }
    return `${sen < 0 ? '-' : ''}RM ${f.format(Math.abs(sen) / 100)}`;
};

/** "12.5" → 1250; blank or invalid → 0 */
export const toSen = (value: string | number | null | undefined): number => {
    const n = Number.parseFloat(String(value ?? ''));
    return Number.isFinite(n) ? Math.round(n * 100) : 0;
};

/** 1250 → "12.50" for editing in an input */
export const fromSen = (sen: number): string => (sen / 100).toFixed(2);

export const formatDate = (iso: string): string =>
    new Date(iso.length === 10 ? `${iso}T00:00:00` : iso).toLocaleDateString(
        intlLocale(),
        { day: '2-digit', month: 'short', year: 'numeric' },
    );

export const formatDateTime = (iso: string): string =>
    new Date(iso).toLocaleString(intlLocale(), {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
