import { computed, onMounted, ref, shallowRef } from 'vue';
import type { ComputedRef } from 'vue';
import { isIos, isStandalone } from './platform';

/** Chromium's install prompt event (not in the DOM typings). */
interface BeforeInstallPromptEvent extends Event {
    prompt(): Promise<void>;
    readonly userChoice: Promise<{
        outcome: 'accepted' | 'dismissed';
        platform: string;
    }>;
}

export type InstallOutcome = 'accepted' | 'dismissed' | 'unavailable';

const deferredPrompt = shallowRef<BeforeInstallPromptEvent | null>(null);
const standalone = ref(false);
const ios = ref(false);

let started = false;

/**
 * Catches the browser's install prompt as early as it fires, so a button can
 * offer it later. Client only, once; app.ts calls it at startup.
 */
export function setupInstallPrompt(): void {
    if (typeof window === 'undefined' || started) {
        return;
    }

    started = true;
    standalone.value = isStandalone();
    ios.value = isIos();

    window.addEventListener('beforeinstallprompt', (event) => {
        // No browser banner: the app offers installing where it fits.
        event.preventDefault();
        deferredPrompt.value = event as BeforeInstallPromptEvent;
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt.value = null;
    });

    window
        .matchMedia?.('(display-mode: standalone)')
        .addEventListener?.('change', () => {
            standalone.value = isStandalone();
        });
}

async function promptInstall(): Promise<InstallOutcome> {
    const event = deferredPrompt.value;

    if (!event) {
        return 'unavailable';
    }

    // An install prompt event can only be shown once.
    deferredPrompt.value = null;

    try {
        await event.prompt();

        return (await event.userChoice).outcome;
    } catch {
        return 'unavailable';
    }
}

/**
 * Installing the web app.
 * - canInstall: the browser offered its install prompt (Chromium);
 *   promptInstall() shows it, from a tap.
 * - isStandalone: already running as the installed app.
 * - isIos: iOS/iPadOS, where installing is manual (Share → Add to Home
 *   Screen), and required for push.
 *
 * All false until mounted, so the server render and hydration agree.
 */
export function useInstallPrompt(): {
    canInstall: ComputedRef<boolean>;
    promptInstall: () => Promise<InstallOutcome>;
    isStandalone: ComputedRef<boolean>;
    isIos: ComputedRef<boolean>;
} {
    const mounted = ref(false);

    onMounted(() => {
        setupInstallPrompt();
        mounted.value = true;
    });

    return {
        canInstall: computed(
            () =>
                mounted.value &&
                deferredPrompt.value !== null &&
                !standalone.value,
        ),
        promptInstall,
        isStandalone: computed(() => mounted.value && standalone.value),
        isIos: computed(() => mounted.value && ios.value),
    };
}
