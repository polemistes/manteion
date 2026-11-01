export type ThemeMode = 'light' | 'dark' | 'system';

/** The look of the whole app (App\Enums\ThemeStyle). */
export type ThemeStyle = 'classic' | 'aegean' | 'taverna';

export interface Theme {
    mode: ThemeMode;
    style: ThemeStyle;
}

export const DEFAULT_STYLE: ThemeStyle = 'classic';

/**
 * The browser UI colour (address bar, an installed app's title bar, the
 * Android task switcher) for each skin and resolved mode: what sits behind
 * the top of the page, the canvas or (Taverna) the painted navigation.
 * ThemeStyle::browserColors() on the server and the `theme-color` metas in
 * resources/views/app.blade.php use the same values.
 */
export const themeColors: Record<ThemeStyle, { light: string; dark: string }> =
    {
        classic: { light: '#F7F3EC', dark: '#14100D' },
        aegean: { light: '#EFE5D3', dark: '#10171F' },
        taverna: { light: '#1F5FB5', dark: '#7F5612' },
    };

/** Whether dark icons read on a colour (the system bars' style). */
export function isLightColor(hex: string): boolean {
    const channel = (offset: number): number => {
        const value = parseInt(hex.slice(offset, offset + 2), 16) / 255;

        return value <= 0.03928
            ? value / 12.92
            : ((value + 0.055) / 1.055) ** 2.4;
    };

    return (
        0.2126 * channel(1) + 0.7152 * channel(3) + 0.0722 * channel(5) > 0.4
    );
}

const darkQuery = (): MediaQueryList | null =>
    typeof window !== 'undefined' && 'matchMedia' in window
        ? window.matchMedia('(prefers-color-scheme: dark)')
        : null;

let current: Theme | null = null;

/**
 * Stamps the resolved light/dark mode and the skin on the root element.
 * "system" follows the OS preference and keeps following it as it changes.
 * The server renders the same attributes for the first paint (see
 * app.blade.php); this keeps them right after a settings change or a
 * system change without a reload.
 */
export function applyTheme(theme: Theme | undefined): void {
    if (typeof document === 'undefined' || !theme) {
        return;
    }

    current = theme;

    const root = document.documentElement;

    root.dataset.mode =
        theme.mode === 'system'
            ? darkQuery()?.matches
                ? 'dark'
                : 'light'
            : theme.mode;
    root.dataset.skin = theme.style;

    syncThemeColor(theme);
}

function styleOf(value: string | undefined): ThemeStyle {
    return value && value in themeColors
        ? (value as ThemeStyle)
        : DEFAULT_STYLE;
}

/**
 * The browser colour for what the root element shows right now, for the
 * Android shell's window colour.
 */
export function currentBrowserColor(): string {
    const root = document.documentElement;

    return themeColors[styleOf(root.dataset.skin)][
        root.dataset.mode === 'dark' ? 'dark' : 'light'
    ];
}

/**
 * The `theme-color` metas come as a light and a dark one, each limited to
 * its `prefers-color-scheme`. That is right for "system"; a forced mode
 * paints both with its own colour, so the browser UI matches the page
 * whatever the OS prefers.
 */
function syncThemeColor(theme: Theme): void {
    const colors = themeColors[styleOf(theme.style)];

    document
        .querySelectorAll<HTMLMetaElement>('meta[name="theme-color"]')
        .forEach((meta) => {
            const scheme = meta.media.includes('dark') ? 'dark' : 'light';
            const color = colors[theme.mode === 'system' ? scheme : theme.mode];

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
