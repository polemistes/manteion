export interface TagOption {
    id: number;
    name: string;
    slug: string;
}

export interface CategoryOption {
    id: number;
    name: string;
    slug: string;
    children: TagOption[];
}

export interface ContactOption {
    id: number;
    name: string;
    username: string;
    avatar_url?: string | null;
}

export interface GroupOption {
    id: number;
    name: string;
}

export interface EventSummary {
    id: number;
    name: string;
    place: string | null;
    city: string | null;
    country: string | null;
    starts_at: string;
    visibility: string;
    owner: ContactOption;
    tags: TagOption[];
    my_status: string | null;
    my_attendance_visibility: string | null;
    cover_image_url: string | null;
    attending_count: number;
    interested_count: number;
    distance_km: number | null;
}

/** The filter values as they travel in the query string. */
export interface EventFilterValues {
    tags?: string[];
    owner_filter?: string[];
    group_id?: string;
    near_lat?: string;
    near_lng?: string;
    start_date?: string;
    end_date?: string;
}

/** What the filter panel emits: ready to pass as a visit's data. */
export interface EventFilterQuery {
    [key: string]: string | string[] | number | undefined;
    tags: string[];
    owner_filter: string[];
    group_id: string | undefined;
    near_lat: number | undefined;
    near_lng: number | undefined;
    start_date: string | undefined;
    end_date: string | undefined;
}

export const dateFnsLocales: Record<string, string> = {
    en: 'en-GB',
    nb: 'nb-NO',
};
