/**
 * Set once the user dismissed the offer to turn on notifications, on this
 * device: by the messages list's prompt (PushPrompt) and the app's
 * first-run sheet (AppPushSheet) alike, so neither asks again.
 */
export const PUSH_PROMPT_DISMISSED_KEY = 'manteion.pushPrompt.dismissed';

/** Set once the app's first-run sheet was shown, whatever the answer. */
export const APP_PUSH_ASKED_KEY = 'manteion.appPush.asked';

export function remembered(key: string): boolean {
    try {
        return window.localStorage.getItem(key) === '1';
    } catch {
        return false;
    }
}

export function remember(key: string): void {
    try {
        window.localStorage.setItem(key, '1');
    } catch {
        // Nothing to remember it in; it may ask again.
    }
}
