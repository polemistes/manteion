export type ThemeMode = 'light' | 'dark' | 'system';

export interface Theme {
    mode: ThemeMode;
}

/**
 * The browser UI colour (address bar, an installed app's title bar, the
 * Android task switcher) for each resolved mode: the canvas token, paper in
 * light mode and black glaze in dark. The `theme-color` metas in
 * resources/views/app.blade.php use the same pair.
 */
export const themeColors = {
    light: '#F7F3EC',
    dark: '#14100D',
} as const;

const darkQuery = (): MediaQueryList | null =>
    typeof window !== 'undefined' && 'matchMedia' in window
        ? window.matchMedia('(prefers-color-scheme: dark)')
        : null;

let current: Theme | null = null;

/**
 * Stamps the resolved light/dark mode on the root element. "system" follows
 * the OS preference and keeps following it as it changes. The server renders
 * the same attribute for the first paint (see app.blade.php); this keeps it
 * right after a settings change or a system change without a reload.
 */
export function applyTheme(theme: Theme | undefined): void {
    if (typeof document === 'undefined' || !theme) {
        return;
    }

    current = theme;

    document.documentElement.dataset.mode =
        theme.mode === 'system'
            ? darkQuery()?.matches
                ? 'dark'
                : 'light'
            : theme.mode;

    syncThemeColor(theme.mode);
}

/**
 * The `theme-color` metas come as a light and a dark one, each limited to
 * its `prefers-color-scheme`. That is right for "system"; a forced mode
 * paints both with its own colour, so the browser UI matches the page
 * whatever the OS prefers.
 */
function syncThemeColor(mode: ThemeMode): void {
    document
        .querySelectorAll<HTMLMetaElement>('meta[name="theme-color"]')
        .forEach((meta) => {
            const scheme = meta.media.includes('dark') ? 'dark' : 'light';
            const color = themeColors[mode === 'system' ? scheme : mode];

            if (meta.content !== color) {
                meta.content = color;
            }
        });
}

darkQuery()?.addEventListener('change', () => {
    if (current?.mode === 'system') {
        applyTheme(current);
    }
});
