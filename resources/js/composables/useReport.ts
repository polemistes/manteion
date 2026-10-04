import { reactive } from 'vue';

export type ReportableType =
    'message' | 'event' | 'comment' | 'leaflet' | 'leaflet_response';

interface ReportState {
    isOpen: boolean;
    type: ReportableType | null;
    id: number | null;
}

const state = reactive<ReportState>({ isOpen: false, type: null, id: null });

/** Things reported in this browser session, so their buttons say "Reported". */
const reported = reactive(new Set<string>());

export function useReportState() {
    return state;
}

/** Opens the one report dialog (mounted by the shell) for this item. */
export function report(type: ReportableType, id: number): void {
    state.type = type;
    state.id = id;
    state.isOpen = true;
}

export function markReported(type: ReportableType, id: number): void {
    reported.add(`${type}:${id}`);
}

export function isReported(type: ReportableType, id: number): boolean {
    return reported.has(`${type}:${id}`);
}
