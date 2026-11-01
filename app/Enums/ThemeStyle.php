<?php

namespace App\Enums;

/**
 * The look of the whole app, chosen in Settings → Appearance and stamped on
 * <html> as data-skin. Each skin has a light and a dark mode (ThemeMode).
 * The colours live in resources/css/app.css; only the one the browser
 * paints its own UI with (address bar, task switcher) is repeated here.
 */
enum ThemeStyle: string
{
    /** "Terracotta": paper and ink, the original look. */
    case Classic = 'classic';

    /** Clay, olive, ochre and Greek blue on rough plaster. */
    case Aegean = 'aegean';

    public static function default(): self
    {
        return self::Classic;
    }

    /**
     * The canvas colour of each mode, for the `theme-color` metas and the
     * Android window. Keep in step with --color-canvas in app.css and
     * `themeColors` in resources/js/theme.ts.
     *
     * @return array{light: string, dark: string}
     */
    public function browserColors(): array
    {
        return match ($this) {
            self::Classic => ['light' => '#F7F3EC', 'dark' => '#14100D'],
            self::Aegean => ['light' => '#EFE5D3', 'dark' => '#10171F'],
        };
    }
}
