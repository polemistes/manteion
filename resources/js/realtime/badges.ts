import type { Page } from '@inertiajs/core';
import { usePage } from '@inertiajs/vue3';
import { computed, readonly, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { show as badgesShow } from '@/routes/badges';
import type { Badges } from '@/types';
import { connectionState, onReconnect } from './echo';
import { requestJson } from './http';

/** How often to ask while the WebSocket is down and broadcasts can't arrive. */
const FALLBACK_POLL_MS = 60_000;

/** Focus and visibility changes come in bursts; ask at most this often. */
const AMBIENT_REFRESH_MS = 3_000;

/** How long a higher Messages count waits while the chat on screen reads it. */
const READ_HOLD_MS = 1_500;

const none: Badges = { messages: 0, activity: 0, requests: 0 };

const store = ref<Badges>({ ...none });
const storeView = readonly(store);
const storeTotal = computed(
    () => store.value.messages + store.value.activity + store.value.requests,
);

let started = false;
let signedIn = false;
// Bumped whenever the user changes, so an answer for the previous one is dropped.
let generation = 0;
let lastPageBadges: unknown;
let pendingPageBadges: Badges | null = null;
let inFlight: Promise<void> | null = null;
let refreshAgain = false;
let lastRefreshAt = 0;
let refreshTimer: ReturnType<typeof setTimeout> | undefined;
let pollTimer: ReturnType<typeof setInterval> | undefined;
let holdUntil = 0;
let held: Badges | null = null;
let holdTimer: ReturnType<typeof setTimeout> | undefined;

function count(value: unknown): number {
    const number = Number(value);

    return Number.isFinite(number) && number > 0 ? Math.floor(number) : 0;
}

/** Complete, non-negative counts from whatever arrived. */
function normalize(value: unknown): Badges {
    const source = (value ?? {}) as Partial<Record<keyof Badges, unknown>>;

    return {
        messages: count(source.messages),
        activity: count(source.activity),
        requests: count(source.requests),
    };
}

function same(a: Badges, b: Badges): boolean {
    return (
        a.messages === b.messages &&
        a.activity === b.activity &&
        a.requests === b.requests
    );
}

function apply(next: Badges): void {
    if (!same(next, store.value)) {
        store.value = next;
    }
}

function set(next: Badges): void {
    if (Date.now() < holdUntil && next.messages > store.value.messages) {
        // The other counts stay live; only the higher Messages one waits.
        held = next;
        apply({ ...next, messages: store.value.messages });

        return;
    }

    held = null;
    apply(next);
}

function releaseHold(): void {
    clearTimeout(holdTimer);
    holdUntil = 0;

    const next = held;

    held = null;

    if (next) {
        apply(next);
    }
}

/**
 * A message arrived in the chat on screen, which marks it read at once.
 * For a moment a higher Messages count waits, so the badge doesn't go up
 * and straight back down; if it is still higher when the moment is over
 * (the read failed, or another chat got a message), it shows then.
 */
export function holdMessagesBadge(): void {
    if (typeof window === 'undefined') {
        return;
    }

    holdUntil = Date.now() + READ_HOLD_MS;
    clearTimeout(holdTimer);
    holdTimer = setTimeout(releaseHold, READ_HOLD_MS);
}

/**
 * The live badge counts, and their sum. One store for the whole app: it
 * starts from the page props and follows `.badges.updated` broadcasts, page
 * visits and catch-up requests, so every badge and the favicon dot agree.
 * Call it from a component's setup (on the server it reads the request's
 * own page props).
 */
export function useBadges(): {
    badges: Readonly<Ref<Readonly<Badges>>>;
    total: ComputedRef<number>;
} {
    if (typeof window === 'undefined') {
        const page = usePage();
        const badges = computed(() => normalize(page.props.badges));

        return {
            badges,
            total: computed(
                () =>
                    badges.value.messages +
                    badges.value.activity +
                    badges.value.requests,
            ),
        };
    }

    return { badges: storeView, total: storeTotal };
}

/** The store's total, for modules outside components (the favicon dot). */
export const badgeTotal: ComputedRef<number> = storeTotal;

/**
 * Counts that are known to be current: a `.badges.updated` broadcast or a
 * GET /badges answer.
 */
export function receiveBadges(value: unknown): void {
    if (!signedIn) {
        return;
    }

    pendingPageBadges = null;
    set(normalize(value));
}

/**
 * Asks the server for the current counts (GET /badges). Calls that arrive
 * while one is under way are folded into one more request after it.
 */
export function refreshBadges(): Promise<void> {
    if (typeof window === 'undefined' || !signedIn) {
        return Promise.resolve();
    }

    if (inFlight) {
        refreshAgain = true;

        return inFlight;
    }

    const asked = generation;

    inFlight = (async () => {
        try {
            do {
                refreshAgain = false;
                lastRefreshAt = Date.now();

                const counts = await requestJson<Badges>(badgesShow());

                if (asked !== generation) {
                    return;
                }

                if (counts) {
                    receiveBadges(counts);
                }
            } while (refreshAgain);
        } catch {
            // Offline, or signed out meanwhile: the last page's own counts
            // are the best there is.
            if (asked === generation && pendingPageBadges) {
                set(pendingPageBadges);
            }
        } finally {
            pendingPageBadges = null;
            inFlight = null;
        }
    })();

    return inFlight;
}

function scheduleRefresh(ambient = false): void {
    if (ambient && Date.now() - lastRefreshAt < AMBIENT_REFRESH_MS) {
        return;
    }

    clearTimeout(refreshTimer);
    refreshTimer = setTimeout(() => void refreshBadges(), ambient ? 250 : 0);
}

/**
 * Starts over for a (possibly different) user from a page's props: a login,
 * a logout, the start of an impersonation.
 */
export function resetBadges(page: Page, isSignedIn: boolean): void {
    generation++;
    signedIn = isSignedIn;
    lastPageBadges = page.props.badges;
    pendingPageBadges = null;
    clearTimeout(holdTimer);
    holdUntil = 0;
    held = null;
    set(isSignedIn ? normalize(page.props.badges) : { ...none });
}

/**
 * The counts a visit's response brought. A partial reload that didn't ask
 * for them keeps the previous object, so it is skipped. Counts that differ
 * from the store are confirmed with GET /badges before they are shown: a
 * prefetched page, or a response racing a broadcast, can carry older ones.
 */
export function syncBadgesFromPage(page: Page): void {
    const fresh = page.props.badges;

    if (fresh === undefined || fresh === lastPageBadges) {
        return;
    }

    lastPageBadges = fresh;

    const counts = normalize(fresh);

    if (!signedIn || same(counts, store.value)) {
        return;
    }

    pendingPageBadges = counts;
    scheduleRefresh();
}

function startPolling(): void {
    if (pollTimer === undefined) {
        pollTimer = setInterval(() => void refreshBadges(), FALLBACK_POLL_MS);
    }
}

function stopPolling(): void {
    clearInterval(pollTimer);
    pollTimer = undefined;
}

/**
 * Catches up whenever broadcasts may have been missed: the window regains
 * focus or becomes visible, the device comes back online, the page returns
 * from the back/forward cache, or the WebSocket reconnects; and polls every
 * minute while the WebSocket is down. Client only, once.
 */
export function setupBadges(): void {
    if (typeof window === 'undefined' || started) {
        return;
    }

    started = true;

    window.addEventListener('focus', () => scheduleRefresh(true));
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            scheduleRefresh(true);
        }
    });
    window.addEventListener('online', () => scheduleRefresh());
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            scheduleRefresh();
        }
    });
    onReconnect(() => scheduleRefresh());

    watch(
        connectionState,
        (state) => {
            if (state === 'disconnected') {
                startPolling();
            } else {
                stopPolling();
            }
        },
        { immediate: true },
    );
}
