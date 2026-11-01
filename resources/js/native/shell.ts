import { App } from '@capacitor/app';
import { Capacitor, SystemBars, SystemBarsStyle } from '@capacitor/core';
import type { Page } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { toast } from '@/lib/toast';
import { dispatchPushMessage } from '@/pwa/serviceWorker';
import { refreshBadges } from '@/realtime';
import { currentBrowserColor } from '@/theme';
import type { NotificationCategory } from '@/types';
import { ManteionPush, ManteionShell } from './plugin';
import type { NativeChannel } from './plugin';
import { refreshRegistration } from './push';
import { isNewerVersion, latestAndroidRelease } from './release';

type Translate = (key: string, named?: Record<string, unknown>) => string;

/** The notification channels, grouped as Settings → Notifications groups them. */
const CHANNEL_GROUPS: { id: string; categories: NotificationCategory[] }[] = [
    {
        id: 'messages',
        categories: ['direct_messages', 'group_messages', 'video_calls'],
    },
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
 * The first Android app whose camera requests reach a camera app: from
 * 1.2 its manifest says it looks for one (Android 11 hides other apps'
 * screens otherwise), and older ones open the photo picker instead.
 */
const CAMERA_SINCE_ANDROID_VERSION = '1.2';

let photoCapture: Promise<boolean> | null = null;

/**
 * Whether asking for a photo from the camera opens a camera here. Browsers
 * decide for themselves, so yes. The Android app from 1.2 on. Not the iOS
 * app, which has no camera usage text yet (iOS would close it).
 */
export function canTakePhotos(): Promise<boolean> {
    if (!isApp()) {
        return Promise.resolve(true);
    }

    photoCapture ??=
        Capacitor.getPlatform() === 'android'
            ? App.getInfo()
                  .then(
                      (info) =>
                          !isNewerVersion(
                              CAMERA_SINCE_ANDROID_VERSION,
                              info.version,
                          ),
                  )
                  .catch(() => false)
            : Promise.resolve(false);

    return photoCapture;
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
    // itself with env(safe-area-inset-top)); their icons follow its mode and
    // the window colour its skin.
    const syncBars = (): void => {
        const dark = document.documentElement.dataset.mode === 'dark';

        void SystemBars.setStyle({
            style: dark ? SystemBarsStyle.Dark : SystemBarsStyle.Light,
        }).catch(() => undefined);
        void ManteionShell.setWindowColor({
            color: currentBrowserColor(),
        }).catch(() => undefined);
    };

    syncBars();
    new MutationObserver(syncBars).observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-mode', 'data-skin'],
    });
}

const UPDATE_CHECKED_KEY = 'manteion.appUpdate.checkedAt';
const UPDATE_SHOWN_KEY = 'manteion.appUpdate.shown';
const UPDATE_CHECK_INTERVAL_MS = 24 * 60 * 60 * 1000;

function stored(key: string): string | null {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

function store(key: string, value: string): void {
    try {
        window.localStorage.setItem(key, value);
    } catch {
        // Asked again next time.
    }
}

/**
 * Once a day, asks GitHub for the newest app; while it is newer than this
 * one, says so once a day, with the download. Never forced; Settings shows
 * the same at any time.
 */
async function offerUpdate(page: Page, t: Translate): Promise<void> {
    const checkedAt = Number(stored(UPDATE_CHECKED_KEY)) || 0;

    if (Date.now() - checkedAt < UPDATE_CHECK_INTERVAL_MS) {
        return;
    }

    store(UPDATE_CHECKED_KEY, String(Date.now()));

    const androidApp = page.props.androidApp as
        { downloadUrl: string; latestReleaseApi: string } | undefined;

    if (!androidApp) {
        return;
    }

    const [installed, latest] = await Promise.all([
        App.getInfo().catch(() => null),
        latestAndroidRelease(androidApp.latestReleaseApi),
    ]);

    if (
        !installed ||
        !latest ||
        !isNewerVersion(latest.version, installed.version)
    ) {
        return;
    }

    // "version:when" of the last notice; the same version again only after a day.
    const [shownVersion, shownAt] = (stored(UPDATE_SHOWN_KEY) ?? '').split(':');

    if (
        shownVersion === latest.version &&
        Date.now() - (Number(shownAt) || 0) < UPDATE_CHECK_INTERVAL_MS
    ) {
        return;
    }

    store(UPDATE_SHOWN_KEY, `${latest.version}:${Date.now()}`);
    toast({
        title: t('app.update.title'),
        description: t('app.update.body', { version: latest.version }),
        icon: Download,
        duration: 15_000,
        action: {
            label: t('app.update.action'),
            // Another site: the app hands it to the browser, which downloads it.
            onClick: () => window.location.assign(androidApp.downloadUrl),
        },
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
 * page hear of a push, and keeping the device's FCM token registered. Also
 * the notice of a newer version of the app.
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
    void offerUpdate(initialPage, t);

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

    // A link to the site opened while the app runs (App Links): its page.
    void App.addListener('appUrlOpen', ({ url }) => {
        const link = new URL(url);

        if (link.hostname !== 'www.manteion.no') {
            return;
        }

        const path = link.pathname + link.search + link.hash;

        if (path !== window.location.pathname + window.location.search) {
            router.visit(path);
        }
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
