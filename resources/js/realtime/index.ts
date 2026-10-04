import type { Page, SharedPageProps } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { forgetSubscription } from '@/pwa/push';
import { clearNotifications } from '@/pwa/serviceWorker';
import { logout } from '@/routes';
import type { Badges, IncomingMessage } from '@/types';
import {
    emitActivity,
    emitIncomingMessage,
    setupMessageSound,
} from './activity';
import { setupAttention } from './attention';
import {
    receiveBadges,
    resetBadges,
    setupBadges,
    syncBadgesFromPage,
} from './badges';
import { joinPrivateChannel, setupEcho } from './echo';
import type { PrivateChannel } from './echo';

export {
    isMessageSoundEnabled,
    onActivity,
    onIncomingMessage,
    playMessageSound,
    setMessageSoundEnabled,
} from './activity';
export { refreshBadges, useBadges } from './badges';
export {
    toMessageSummary,
    useConversationChannel,
    viewedConversationId,
} from './conversation';
export type { ConversationChannelOptions } from './conversation';
export { connectionState, onReconnect } from './echo';
export type { ConnectionState } from './echo';
export { useEventChannel } from './eventChannel';
export { RequestError, requestJson } from './http';

let started = false;
let userId: number | null = null;
let userChannel: { channel: PrivateChannel; release: () => void } | null = null;
let signingOut = false;

const handleBadges = (counts: Badges): void => receiveBadges(counts);

const handleIncomingMessage = (message: IncomingMessage): void =>
    emitIncomingMessage(message);

const handleNotification = (notification: Record<string, unknown>): void =>
    emitActivity(notification);

function pageUserId(page: Page): number | null {
    const auth = page.props.auth as SharedPageProps['auth'] | undefined;
    const id = auth?.user?.id;

    return typeof id === 'number' ? id : null;
}

/**
 * Moves the user channel (badges, incoming messages, activity) and the badge
 * store over to whoever the page says is signed in: on load, login, logout
 * and impersonation. Only server responses count — a page restored from
 * history can show someone the session no longer is.
 */
function follow(page: Page): void {
    const next = pageUserId(page);

    if (next === userId) {
        return;
    }

    userId = next;

    if (userChannel) {
        userChannel.channel
            .stopListening('.badges.updated', handleBadges)
            .stopListening('.message.received', handleIncomingMessage)
            .stopListeningForNotification(handleNotification);
        userChannel.release();
        userChannel = null;
    }

    resetBadges(page, next !== null);

    if (next === null) {
        if (signingOut) {
            // Logged out on purpose (not a session that ran out): on a
            // shared browser the next person must not get the previous
            // user's pushes or see their notifications.
            clearNotifications();
            void forgetSubscription();
        }

        return;
    }

    userChannel = joinPrivateChannel(`App.Models.User.${next}`);
    userChannel?.channel
        .listen('.badges.updated', handleBadges)
        .listen('.message.received', handleIncomingMessage)
        .notification(handleNotification);
}

/**
 * Starts the real-time client: Echo, the live badge store, the tab title,
 * favicon and app badge, the message sound, and the signed-in user's
 * channel. Call once, on the client, with the initial page (app.ts).
 */
export function setupRealtime(initialPage: Page): void {
    if (typeof window === 'undefined' || started) {
        return;
    }

    started = true;

    setupEcho();
    follow(initialPage);
    setupBadges();
    setupAttention();
    setupMessageSound();

    const isLogout = (visit: { method: string; url: URL }): boolean =>
        visit.method === 'post' && visit.url.pathname === logout.url();

    router.on('before', (event) => {
        if (isLogout(event.detail.visit)) {
            signingOut = true;
        }
    });

    router.on('success', (event) => {
        follow(event.detail.page);
        syncBadgesFromPage(event.detail.page);
    });

    // After the logout's own success (or failure), whatever it was.
    router.on('finish', (event) => {
        if (isLogout(event.detail.visit)) {
            signingOut = false;
        }
    });
}
