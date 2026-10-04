---
paths:
  - resources/css/app.css
---

# Css

## Leaflet's stylesheet loads after app.css
Components import 'leaflet/dist/leaflet.css', which lands after app.css, so an override of equal weight loses (the map credit stayed white in dark mode). Scope map overrides as `.map-tiles.leaflet-container …` (both classes are on the map element) to outrank Leaflet's own `.leaflet-container …` rules.
