import { useI18n } from 'vue-i18n';
import { intlLocales } from '@/lib/dates';

const regionNames = new Map<string, Intl.DisplayNames | null>();

function regionNamesFor(locale: string): Intl.DisplayNames | null {
    if (!regionNames.has(locale)) {
        try {
            regionNames.set(
                locale,
                new Intl.DisplayNames([locale], {
                    type: 'region',
                    fallback: 'none',
                }),
            );
        } catch {
            regionNames.set(locale, null);
        }
    }

    return regionNames.get(locale) ?? null;
}

/**
 * A country in the reader's language from its ISO code ("NO" is "Norge" in
 * Norwegian, "Norway" in English), or the name as it was stored when there
 * is no code or no name for it.
 */
export function countryName(
    locale: string,
    code: string | null,
    stored: string | null,
): string | null {
    if (code) {
        try {
            const name = regionNamesFor(locale)?.of(code.toUpperCase());

            if (name) {
                return name;
            }
        } catch {
            // Not a region code: the stored name it is.
        }
    }

    return stored;
}

/** Place lines in the reader's language. */
export function usePlaces() {
    const { locale } = useI18n();

    /** "Oslo, Norge": the city, then the country. Empty when neither is known. */
    const cityAndCountry = (
        city: string | null,
        country: string | null,
        countryCode: string | null,
    ): string =>
        [
            city,
            countryName(
                intlLocales[locale.value] ?? locale.value,
                countryCode,
                country,
            ),
        ]
            .filter(Boolean)
            .join(', ');

    return { cityAndCountry };
}
