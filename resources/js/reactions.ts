export type ReactableType = 'leaflet' | 'leaflet_response' | 'message';

export interface ReactionSummary {
    emoji: string;
    count: number;
    reacted: boolean;
}
