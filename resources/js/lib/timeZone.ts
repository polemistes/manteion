import type { Page, SharedPageProps } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { requestJson } from '@/realtime';
import { update } from '@/routes/time-zone';

/**
 * Tells the server which time zone this device is in when it differs from
 * the one the page was written in, then reloads the page's data so every
 * time (and "today") follows. The server needs to know, rather than the
 * browser converting by itself, so its render and the browser's agree.
 * Not while an administrator looks through someone else's account.
 */
export async function reportTimeZone(page: Page): Promise<void> {
    const props = page.props as Page['props'] & SharedPageProps;

    if (!props.auth?.user || props.impersonating) {
        return;
    }

    let device: string | undefined;

    try {
        device = Intl.DateTimeFormat().resolvedOptions().timeZone;
    } catch {
        return;
    }

    if (!device || device === props.timezone) {
        return;
    }

    try {
        await requestJson(update(), { timezone: device });
        router.reload();
    } catch {
        // A zone the server does not know: the page stays as it was.
    }
}
