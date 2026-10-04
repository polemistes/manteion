import { ref } from 'vue';

export interface NominatimAddress {
    city?: string;
    town?: string;
    village?: string;
    country?: string;
    country_code?: string;
    [key: string]: string | undefined;
}

export interface NominatimResult {
    display_name: string;
    lat: string;
    lon: string;
    address?: NominatimAddress;
}

const SEARCH_DEBOUNCE_MS = 500;
export const MIN_QUERY_LENGTH = 3;

export function cityFromAddress(address?: NominatimAddress): string | null {
    return address?.city ?? address?.town ?? address?.village ?? null;
}

export function countryFromAddress(address?: NominatimAddress): string | null {
    return address?.country ?? null;
}

/** The ISO country code ("NO"), which Nominatim gives in lower case. */
export function countryCodeFromAddress(
    address?: NominatimAddress,
): string | null {
    const code = address?.country_code;

    return code && /^[a-z]{2}$/i.test(code) ? code.toUpperCase() : null;
}

async function fetchNominatim(
    path: string,
    params: Record<string, string>,
    language: string,
): Promise<unknown> {
    const url = new URL(`https://nominatim.openstreetmap.org/${path}`);
    url.search = new URLSearchParams({
        format: 'jsonv2',
        addressdetails: '1',
        'accept-language': language,
        ...params,
    }).toString();

    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error(`Nominatim request failed: ${response.status}`);
    }

    return response.json();
}

/** The address at a point, named in the given language ("nb", "en"). */
export async function reverseGeocode(
    lat: number,
    lng: number,
    language: string,
): Promise<NominatimResult | null> {
    try {
        const result = await fetchNominatim(
            'reverse',
            {
                lat: String(lat),
                lon: String(lng),
            },
            language,
        );

        return result as NominatimResult;
    } catch {
        return null;
    }
}

/** Place search, with names in the given language ("nb", "en"). */
export function useNominatimSearch(language: () => string) {
    const results = ref<NominatimResult[]>([]);
    const searching = ref(false);
    const error = ref(false);

    let debounceTimer: ReturnType<typeof setTimeout> | null = null;
    let requestId = 0;

    async function runSearch(term: string): Promise<void> {
        const thisRequestId = ++requestId;
        searching.value = true;
        error.value = false;

        try {
            const data = await fetchNominatim(
                'search',
                {
                    q: term,
                    limit: '5',
                },
                language(),
            );

            if (thisRequestId === requestId) {
                results.value = data as NominatimResult[];
            }
        } catch {
            if (thisRequestId === requestId) {
                results.value = [];
                error.value = true;
            }
        } finally {
            if (thisRequestId === requestId) {
                searching.value = false;
            }
        }
    }

    function search(term: string): void {
        const trimmed = term.trim();

        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }

        if (trimmed.length < MIN_QUERY_LENGTH) {
            results.value = [];
            searching.value = false;

            return;
        }

        // Counts as searching from the first keystroke, so the UI never
        // reports "no results" for a query that hasn't been sent yet.
        searching.value = true;
        error.value = false;

        debounceTimer = setTimeout(() => {
            void runSearch(trimmed);
        }, SEARCH_DEBOUNCE_MS);
    }

    function clearResults(): void {
        results.value = [];
    }

    return { results, searching, error, search, clearResults };
}
