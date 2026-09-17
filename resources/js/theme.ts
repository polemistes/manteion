export type ThemeStyle = 'classic' | 'soft';
export type ThemeMode = 'light' | 'dark' | 'system';

export interface Theme {
    style: ThemeStyle;
    mode: ThemeMode;
}

const darkQuery = (): MediaQueryList | null =>
    typeof window !== 'undefined' && 'matchMedia' in window
        ? window.matchMedia('(prefers-color-scheme: dark)')
        : null;

let current: Theme | null = null;

/**
 * Stamps the style and the resolved light/dark mode on the root element.
 * "system" follows the OS preference and keeps following it as it changes.
 * The server renders the same attributes for the first paint (see
 * app.blade.php); this keeps them right after a profile save or a system
 * change without a reload.
 */
export function applyTheme(theme: Theme): void {
    if (typeof document === 'undefined') {
        return;
    }

    current = theme;

    const root = document.documentElement;
    root.dataset.style = theme.style;
    root.dataset.mode =
        theme.mode === 'system'
            ? darkQuery()?.matches
                ? 'dark'
                : 'light'
            : theme.mode;
}

darkQuery()?.addEventListener('change', () => {
    if (current?.mode === 'system') {
        applyTheme(current);
    }
});
