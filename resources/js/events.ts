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

export type AttendanceStatus =
    'no_response' | 'attending' | 'interested' | 'not_attending';

export type AttendanceVisibility = 'everyone' | 'friends' | 'none';

/** An event image with what a page needs to frame it (EventImage::presentation). */
export interface EventCover {
    id: number;
    url: string;
    width: number | null;
    height: number | null;
    /** A photo may be cropped to fill its frame; a poster is always shown whole. */
    shape: 'photo' | 'poster';
    fit: 'cover' | 'contain' | null;
}

export interface FacePerson {
    id: number;
    name: string;
    avatar_url: string | null;
}

/** Who invited the viewer, while the invitation is still unanswered. */
export interface EventInvitation {
    by: FacePerson | null;
    at: string;
}

/** A venue or organisation hosting an event (App\Models\Organizer::summary). */
export interface OrganizerSummary {
    id: number;
    name: string;
    handle: string;
    avatar_url: string | null;
}

export interface EventSummary {
    id: number;
    name: string;
    description: string;
    place: string | null;
    city: string | null;
    country: string | null;
    country_code: string | null;
    latitude: number | null;
    longitude: number | null;
    starts_at: string;
    ends_at: string | null;
    visibility: string;
    owner: ContactOption;
    /** The organizer hosting it, when a spokesperson made it for one. */
    organizer: OrganizerSummary | null;
    is_host: boolean;
    tags: TagOption[];
    my_status: AttendanceStatus | null;
    my_attendance_visibility: AttendanceVisibility | null;
    invitation: EventInvitation | null;
    cover: EventCover | null;
    attending_count: number;
    interested_count: number;
    going: FacePerson[];
    distance_km: number | null;
}

/** The filter values as they travel in the query string. */
export interface EventFilterValues {
    mine?: string;
    tags?: string[];
    owner_filter?: string[];
    group_id?: string;
    near_lat?: string;
    near_lng?: string;
    start_date?: string;
    end_date?: string;
}
