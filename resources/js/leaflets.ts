export type LeafletVisibility =
    'official' | 'friends_and_followers' | 'friends' | 'private';

export const leafletVisibilities: LeafletVisibility[] = [
    'official',
    'friends_and_followers',
    'friends',
    'private',
];

export interface LeafletSummary {
    id: number;
    body: string;
    visibility: LeafletVisibility;
    created_at: string;
    is_edited: boolean;
    owner: {
        id: number;
        name: string;
        username: string;
        avatar_url: string | null;
    };
    image_url: string | null;
    link: {
        url: string;
        host: string | null;
        title: string | null;
        description: string | null;
        image_url: string | null;
    } | null;
    can_edit: boolean;
    can_delete: boolean;
    can_report: boolean;
}
