import type { EventFilterValues } from '@/events';
import type { EventFilterState } from './EventFiltersForm.vue';

/** The filter form's state from the query string the page was loaded with. */
export function stateFromFilters(
    filters: EventFilterValues & { near_label?: string },
    today: string,
): EventFilterState {
    return {
        tags: filters.tags ?? [],
        owner_filter: filters.owner_filter ?? [],
        group_id: filters.group_id ?? '',
        start_date: filters.start_date || today,
        end_date: filters.end_date ?? '',
        near_lat: filters.near_lat ? Number(filters.near_lat) : null,
        near_lng: filters.near_lng ? Number(filters.near_lng) : null,
        near_label: filters.near_label ?? null,
    };
}

/**
 * The query string for a visit: only what narrows something, so a plain
 * feed stays at /events.
 */
export function queryFromState(
    state: EventFilterState,
    today: string,
    extra: Record<string, string | undefined> = {},
): Record<string, string | string[]> {
    const query: Record<string, string | string[]> = {};

    if (state.tags.length) {
        query.tags = state.tags;
    }

    if (state.owner_filter.length) {
        query.owner_filter = state.owner_filter;
    }

    if (state.group_id) {
        query.group_id = state.group_id;
    }

    if (state.start_date && state.start_date !== today) {
        query.start_date = state.start_date;
    }

    if (state.end_date) {
        query.end_date = state.end_date;
    }

    if (state.near_lat !== null && state.near_lng !== null) {
        query.near_lat = String(state.near_lat);
        query.near_lng = String(state.near_lng);

        if (state.near_label) {
            query.near_label = state.near_label;
        }
    }

    for (const [key, value] of Object.entries(extra)) {
        if (value) {
            query[key] = value;
        }
    }

    return query;
}

/** How many filters the sheet holds, for the "Filters 2" chip. */
export function activeFilterCount(
    state: EventFilterState,
    today: string,
): number {
    return (
        state.tags.length +
        state.owner_filter.length +
        (state.group_id ? 1 : 0) +
        ((state.start_date && state.start_date !== today) || state.end_date
            ? 1
            : 0) +
        (state.near_lat !== null ? 1 : 0)
    );
}

/** A form state with nothing narrowed. */
export function emptyState(today: string): EventFilterState {
    return {
        tags: [],
        owner_filter: [],
        group_id: '',
        start_date: today,
        end_date: '',
        near_lat: null,
        near_lng: null,
        near_label: null,
    };
}
