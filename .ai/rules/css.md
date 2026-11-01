---
paths:
  - resources/css/app.css
---

# Css

## Leaflet's stylesheet loads after app.css
Components import 'leaflet/dist/leaflet.css', which lands after app.css, so an override of equal weight loses (the map credit stayed white in dark mode). Scope map overrides as `.map-tiles.leaflet-container …` (both classes are on the map element) to outrank Leaflet's own `.leaflet-container …` rules.

## Skins are token overrides under html[data-skin], plus textures painted on the utilities
The look of the app (App\Enums\ThemeStyle: classic, aegean) is stamped on <html> as data-skin beside data-mode (Blade for the first paint, applyTheme() in resources/js/theme.ts after). A skin is one block `html[data-skin='x'] { --color-…, --radius-…, --font-display… }` and a full `html[data-skin='x'][data-mode='dark']` block (define every token there, the classic dark block has the same specificity as the skin's light block). Components never branch on the skin. What tokens cannot carry is painted on the generated utilities themselves (`html[data-skin='aegean'] :is(.bg-surface, …) { background-image }`, inline SVG noise generated with feTurbulence). The owner asked to lose a Greek-key frieze along the navigation (2026-11-01): texture yes, ornament no. The canvas colour of each skin and mode must match in three places: ThemeStyle::browserColors(), themeColors in theme.ts, and WebAppShellTest. Check a skin on /dev/kit by setting document.documentElement.dataset.skin in the console.
