import { usePage } from '@inertiajs/vue3';

/** Region-specific Intl locales for numbers, money and dates. */
const INTL_LOCALES: Record<string, string> = {
    en: 'en-MY',
    ms: 'ms-MY',
    zh: 'zh-CN',
};

/**
 * Interface translation. Keys are the English text, so an untranslated string shows in English.
 * Placeholders use Laravel's syntax: t('Showing :from to :to', { from: 1, to: 9 }).
 * Available in templates as $t; usePage() is reactive, so text updates when the language changes.
 */
export function t(
    key: string,
    replace: Record<string, string | number> = {},
): string {
    const translations = (usePage().props.translations ?? {}) as Record<
        string,
        string
    >;

    // Match whole placeholder names, so :to never eats the start of :total.
    return (translations[key] ?? key).replace(
        /:([A-Za-z_]+)/g,
        (match, name: string) =>
            name in replace ? String(replace[name]) : match,
    );
}

export const intlLocale = (): string =>
    INTL_LOCALES[String(usePage().props.locale ?? 'en')] ?? 'en-MY';
