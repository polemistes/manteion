/**
 * Browser and install-state checks shared by the PWA modules. Each one is
 * false on the server; call them from event handlers or after mount.
 */

/** iPhone, iPod or iPad, including iPadOS presenting itself as a Mac. */
export function isIos(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    const userAgent = navigator.userAgent;

    return (
        /iPad|iPhone|iPod/.test(userAgent) ||
        (userAgent.includes('Macintosh') && navigator.maxTouchPoints > 1)
    );
}

/** The iOS/iPadOS version from the user agent, when it says. */
export function iosVersion(): [number, number] | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const match =
        navigator.userAgent.match(/OS (\d+)_(\d+)/) ??
        navigator.userAgent.match(/Version\/(\d+)\.(\d+)/);

    return match ? [Number(match[1]), Number(match[2])] : null;
}

/** Running as an installed app (home screen, dock, app window). */
export function isStandalone(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    const displayModes = [
        'standalone',
        'fullscreen',
        'minimal-ui',
        'window-controls-overlay',
    ];

    return (
        displayModes.some(
            (mode) => window.matchMedia?.(`(display-mode: ${mode})`).matches,
        ) ||
        (navigator as Navigator & { standalone?: boolean }).standalone === true
    );
}

/** Inside the Capacitor app shell, where web push and installing don't apply. */
export function isNativeShell(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    const capacitor = (
        window as unknown as {
            Capacitor?: { isNativePlatform?: () => boolean };
        }
    ).Capacitor;

    return capacitor?.isNativePlatform?.() === true;
}
