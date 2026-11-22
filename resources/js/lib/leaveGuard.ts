/**
 * Where a page asks to hear about the browser's back and forward before
 * Inertia does (see composables/useLeaveGuard.ts). A browser runs the
 * listeners for `popstate` on the window in the order they were added,
 * whatever their capture flag, so the only way to come first is to be
 * added first: app.ts calls installLeaveGuard() before it starts Inertia.
 */
type PopGuard = (event: PopStateEvent) => void;

let active: PopGuard | null = null;

export function installLeaveGuard(): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.addEventListener('popstate', (event) => active?.(event));
}

/** The page's guard for back and forward; null when it has none. */
export function setPopGuard(guard: PopGuard | null): void {
    active = guard;
}
