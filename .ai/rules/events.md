---
paths:
  - resources/js/pages/events/Show.vue
---

# Events

## One Leaflet map per place; reorder with grid, not a hidden copy
Leaflet started in a display:none box draws broken tiles when the box appears (a phone rotated past 640px, a resized window). To show the map in a different position per breakpoint, keep one EventMapCard and move it with grid row/column placement (as Who's coming / About / map do here); never render a second one hidden by CSS.
