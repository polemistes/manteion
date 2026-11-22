---
paths:
  - resources/js/components/events/EventMapCard.vue
  - resources/js/components/LocationPicker.vue
  - resources/js/pages/events/Show.vue
---

# Maps

## Leaflet must not start in a hidden box
Leaflet started in a display:none box lays out its tiles for a 0×0 map, and they show broken when the box appears (a phone rotated past 640px, a resized window). EventMapCard starts the map only once its box has a size and re-measures on resize (ResizeObserver), so a copy hidden at one breakpoint is harmless (the event page has one in the column and one in the xl aside). LocationPicker assumes it is visible; give any new map EventMapCard's treatment.

## The form's map must not take the page's scrolling
LocationPicker sits in a long form. A plain mouse wheel over it scrolls the page: the box around the map stops the wheel event on its way down (`@wheel.capture`, stopPropagation) unless Ctrl or ⌘ is held, and shows "Hold Ctrl and scroll to zoom". Leaflet's own wheel handler stays enabled on purpose: it is what prevents the browser from zooming the whole page on Ctrl + wheel, and enabling it in answer to the event came too late for the first one. On touch screens (pointer: coarse) dragging is off, so one finger scrolls the page and two fingers pinch and move the map; a tap still places the pin. An event's place is shown whenever it has a pin, named or not (Show.vue: placeTitle falls back to the town, then "Marked on the map"); a picked search result fills an empty Place name.
