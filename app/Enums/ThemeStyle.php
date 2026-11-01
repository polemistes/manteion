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

    /** A courtyard: a terracotta wall, ochre tables, a cobalt door. */
    case Taverna = 'taverna';

    public static function default(): self
    {
        return self::Classic;
    }

    /**
     * The colour behind the top of the page in each mode, for the
     * `theme-color` metas and the Android window: the canvas, or the
     * navigation's colour where the top bar is painted (Taverna). Keep in
     * step with app.css and `themeColors` in resources/js/theme.ts.
     *
     * @return array{light: string, dark: string}
     */
    public function browserColors(): array
    {
        return match ($this) {
            self::Classic => ['light' => '#F7F3EC', 'dark' => '#14100D'],
            self::Aegean => ['light' => '#EFE5D3', 'dark' => '#10171F'],
            self::Taverna => ['light' => '#1F5FB5', 'dark' => '#7F5612'],
        };
    }
}
