import { App } from '@capacitor/app';
import { Capacitor, SystemBars, SystemBarsStyle } from '@capacitor/core';
import type { Page } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { dispatchPushMessage } from '@/pwa/serviceWorker';
import { refreshBadges } from '@/realtime';
import { themeColors } from '@/theme';
import type { NotificationCategory } from '@/types';
import { ManteionPush, ManteionShell } from './plugin';
import type { NativeChannel } from './plugin';
import { refreshRegistration } from './push';

type Translate = (key: string, named?: Record<string, unknown>) => string;

/** The notification channels, grouped as Settings → Notifications groups them. */
const CHANNEL_GROUPS: { id: string; categories: NotificationCategory[] }[] = [
    { id: 'messages', categories: ['direct_messages', 'group_messages'] },
    {
        id: 'events',
        categories: ['event_invites', 'event_comments', 'followed_events'],
    },
    {
        id: 'people',
        categories: ['friend_requests', 'followers', 'organizer_invites'],
    },
    { id: 'leaflets', categories: ['leaflet_responses'] },
];

function isApp(): boolean {
    return Capacitor.isNativePlatform();
}

/**
 * The back button and the system bars, inside the Android app. A no-op on
 * the web. Called before the Inertia app starts.
 */
export function setupNativeShell(): void {
    if (!isApp()) {
        return;
    }

    void App.addListener('backButton', ({ canGoBack }) => {
        if (canGoBack) {
            window.history.back();
        } else {
            void App.exitApp();
        }
    });

    // The page draws under the status and navigation bars (its header pads
    // itself with env(safe-area-inset-top)); their icons follow its mode.
    const syncBars = (): void => {
        const dark = document.documentElement.dataset.mode === 'dark';

        void SystemBars.setStyle({
            style: dark ? SystemBarsStyle.Dark : SystemBarsStyle.Light,
        }).catch(() => undefined);
        void ManteionShell.setWindowColor({
            color: themeColors[dark ? 'dark' : 'light'],
        }).catch(() => undefined);
    };

    syncBars();
    new MutationObserver(syncBars).observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-mode'],
    });
}

function userIdOf(page: Page): number | null {
    const auth = page.props.auth as { user?: { id?: unknown } } | undefined;
    const id = auth?.user?.id;

    return typeof id === 'number' ? id : null;
}

function pathOf(url: string): string {
    return new URL(url, window.location.href).pathname;
}

/** The channels and the notifications' own words, in the user's language. */
function configureNotifications(t: Translate): void {
    const channels: NativeChannel[] = CHANNEL_GROUPS.flatMap((group) =>
        group.categories.map((category) => ({
            id: category,
            name: t(`settings.notifications.categories.${category}.title`),
            description: t(
                `settings.notifications.categories.${category}.description`,
            ),
            group: group.id,
            importance:
                group.id === 'messages'
                    ? ('high' as const)
                    : ('default' as const),
        })),
    );

    void ManteionPush.configure({
        groups: CHANNEL_GROUPS.map((group) => ({
            id: group.id,
            name: t(`settings.notifications.groups.${group.id}`),
        })),
        channels,
        strings: {
            you: t('app.notifications.you'),
            reply: t('app.notifications.reply'),
            markRead: t('app.notifications.markRead'),
            replyFailed: t('app.notifications.replyFailed'),
            replyFailedBody: t('app.notifications.replyFailedBody'),
            // The app puts the count in.
            newMessages: t('app.notifications.conversations', { n: '{n}' }),
            newActivity: t('app.notifications.activity', { n: '{n}' }),
        },
    }).catch(() => undefined);
}

/**
 * The app's push notifications, once the first page is known (app.ts):
 * channels in the user's language, the page in front (whose own pushes the
 * app leaves out), opening a tapped notification's page, letting the open
 * page hear of a push, and keeping the device's FCM token registered.
 */
export function startNativePush(initialPage: Page, t: Translate): void {
    if (!isApp()) {
        return;
    }

    let locale = initialPage.props.locale;
    let userId = userIdOf(initialPage);

    configureNotifications(t);
    void ManteionPush.setCurrentPath({ path: pathOf(initialPage.url) });
    void refreshRegistration(userId);

    router.on('navigate', (event) => {
        const page = event.detail.page;

        void ManteionPush.setCurrentPath({ path: pathOf(page.url) });

        if (page.props.locale !== locale) {
            locale = page.props.locale;
            // The page's i18n follows the new language a moment later.
            setTimeout(() => configureNotifications(t), 0);
        }

        if (userIdOf(page) !== userId) {
            userId = userIdOf(page);
            void refreshRegistration(userId);
        }
    });

    void ManteionPush.addListener('tapped', ({ url }) => {
        const target = new URL(url, window.location.href);

        if (target.origin !== window.location.origin) {
            return;
        }

        router.visit(target.pathname + target.search + target.hash);
    });

    void ManteionPush.addListener('received', (data) => {
        dispatchPushMessage(data);
    });

    void ManteionPush.addListener('token', () => {
        void refreshRegistration(userId);
    });

    // Counts may have moved while the app was in the background.
    void App.addListener('resume', () => {
        if (userId !== null) {
            void refreshBadges();
        }
    });
}
