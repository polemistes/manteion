import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';
import { setPopGuard } from '@/lib/leaveGuard';

interface LeaveGuardOptions {
    /** Whether there is something that leaving would lose. */
    when: () => boolean;
    /** Asks; resolves true to leave and lose it. */
    ask: () => Promise<boolean>;
}

type NavigationLike = { currentEntry?: { index: number } | null };

/**
 * Asks before a page with unsaved work is left: by a link or button of the
 * app (the ×, Cancel, the navigation), by the browser's or the phone's back
 * and forward, and (with the browser's own question) by closing or
 * reloading the tab. Sending a form is not leaving; call `allow()` when the
 * work has been saved some other way.
 *
 * Back and forward cannot be refused, only undone: the entry the page moved
 * to is left again at once, before Inertia sees either move (lib/
 * leaveGuard.ts puts this guard ahead of Inertia's own listener), and the
 * question is asked once the person is back where they were. Which way to go back comes
 * from the Navigation API where the browser has it, and is taken to be
 * "back" elsewhere.
 */
export function useLeaveGuard(options: LeaveGuardOptions): {
    allow: () => void;
} {
    let allowed = false;
    let asking = false;
    // History moves this guard started itself, which Inertia must not see.
    let undoing = 0;
    let homeUrl = '';
    let homeIndex: number | null = null;

    const guarding = (): boolean => !allowed && options.when();

    const navigation = (): NavigationLike | undefined =>
        (window as unknown as { navigation?: NavigationLike }).navigation;

    async function decide(leave: () => void): Promise<void> {
        if (asking) {
            return;
        }

        asking = true;

        const agreed = await options.ask();

        asking = false;

        if (agreed) {
            allowed = true;
            leave();
        }
    }

    // What "Leave" will do once the move has been undone.
    let afterUndo: (() => void) | null = null;
    let undoTimer: ReturnType<typeof setTimeout> | undefined;

    function onPopState(event: PopStateEvent): void {
        if (undoing > 0) {
            // Back on the page being left: now ask.
            undoing -= 1;
            event.stopImmediatePropagation();
            clearTimeout(undoTimer);

            const leave = afterUndo;

            afterUndo = null;

            if (leave) {
                void decide(leave);
            }

            return;
        }

        if (!guarding()) {
            return;
        }

        const index = navigation()?.currentEntry?.index ?? null;
        const steps =
            homeIndex !== null && index !== null ? homeIndex - index : 1;

        if (steps === 0) {
            return;
        }

        // Keep Inertia out of it, and go back to the page being left.
        event.stopImmediatePropagation();
        undoing += 1;
        afterUndo = () => window.history.go(-steps);
        window.history.go(steps);

        // Without the Navigation API the direction was a guess: a move
        // forward cannot be undone by another one, and nothing comes back.
        // Then give up guarding and show the page the address now names,
        // rather than swallow the next real move.
        clearTimeout(undoTimer);
        undoTimer = setTimeout(() => {
            if (undoing === 0) {
                return;
            }

            undoing = 0;
            afterUndo = null;
            allowed = true;
            router.visit(window.location.href, { replace: true });
        }, 1500);
    }

    function onBeforeUnload(event: BeforeUnloadEvent): void {
        if (guarding()) {
            event.preventDefault();
        }
    }

    let removeBefore: (() => void) | undefined;

    // Where in the history the page is. Read whenever the person does
    // something on it, not once on mounting: the page can mount before its
    // own entry is the current one, and the number would be another's.
    function notePlace(): void {
        if (undoing === 0) {
            homeUrl = window.location.href;
            homeIndex = navigation()?.currentEntry?.index ?? null;
        }
    }

    onMounted(() => {
        notePlace();
        document.addEventListener('input', notePlace, true);
        document.addEventListener('focusin', notePlace, true);
        document.addEventListener('pointerdown', notePlace, true);

        removeBefore = router.on('before', (event) => {
            const visit = event.detail.visit;

            if (
                !guarding() ||
                visit.method !== 'get' ||
                visit.prefetch ||
                visit.only.length > 0 ||
                visit.url.href === homeUrl
            ) {
                return;
            }

            event.preventDefault();

            void decide(() =>
                router.visit(visit.url, {
                    replace: visit.replace,
                    preserveScroll: visit.preserveScroll,
                    preserveState: visit.preserveState,
                }),
            );
        });

        setPopGuard(onPopState);
        window.addEventListener('beforeunload', onBeforeUnload);
    });

    onBeforeUnmount(() => {
        removeBefore?.();
        clearTimeout(undoTimer);
        document.removeEventListener('input', notePlace, true);
        document.removeEventListener('focusin', notePlace, true);
        document.removeEventListener('pointerdown', notePlace, true);
        setPopGuard(null);
        window.removeEventListener('beforeunload', onBeforeUnload);
    });

    return {
        allow: () => {
            allowed = true;
        },
    };
}
