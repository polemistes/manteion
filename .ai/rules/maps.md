---
paths:
  - resources/js/components/events/EventMapCard.vue
  - resources/js/components/LocationPicker.vue
  - resources/js/pages/events/Show.vue
---

# Maps

## Leaflet must not start in a hidden box
Leaflet started in a display:none box lays out its tiles for a 0×0 map, and they show broken when the box appears (a phone rotated past 640px, a resized window). EventMapCard starts the map only once its box has a size and re-measures on resize (ResizeObserver), so a copy hidden at one breakpoint is harmless (the event page has one in the column and one in the xl aside). LocationPicker assumes it is visible; give any new map EventMapCard's treatment.
