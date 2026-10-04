export type ReactableType =
    | 'comment'
    | 'leaflet'
    | 'leaflet_response'
    | 'message';

export interface ReactionSummary {
    emoji: string;
    count: number;
    reacted: boolean;
}
