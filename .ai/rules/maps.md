---
paths:
  - resources/js/components/events/EventMapCard.vue
  - resources/js/components/LocationPicker.vue
  - resources/js/pages/events/Show.vue
  - 'resources/js/components/events/**'
---

# Maps

## Leaflet must not start in a hidden box
Leaflet started in a display:none box lays out its tiles for a 0×0 map, and they show broken when the box appears (a phone rotated past 640px, a resized window). EventMapCard starts the map only once its box has a size and re-measures on resize (ResizeObserver), so a copy hidden at one breakpoint is harmless (the event page has one in the column and one in the xl aside). LocationPicker assumes it is visible; give any new map EventMapCard's treatment.

## The form's map must not take the page's scrolling
LocationPicker sits in a long form. A plain mouse wheel over it scrolls the page: the box around the map stops the wheel event on its way down (`@wheel.capture`, stopPropagation) unless Ctrl or ⌘ is held, and shows "Hold Ctrl and scroll to zoom". Leaflet's own wheel handler stays enabled on purpose: it is what prevents the browser from zooming the whole page on Ctrl + wheel, and enabling it in answer to the event came too late for the first one. On touch screens (pointer: coarse) dragging is off, so one finger scrolls the page and two fingers pinch and move the map; a tap still places the pin. An event's place is shown whenever it has a pin, named or not (Show.vue: placeTitle falls back to the town, then "Marked on the map"); a picked search result fills an empty Place name.

## One "Where" field: the name is the host's, the pin comes from three ways, nothing is searched until asked
Since 2026-11-22 LocationPicker owns the whole "Where" block of the event form (owner's decision after asking how places unknown to OpenStreetMap, like "My place", would work). The name (`place`, v-model:name) is free text and is never replaced. The pin is set by "Find “name” on the map", by "Search for an address" (the name stays), or by "Mark it on the map" (tap, drag to adjust). The map box only exists once there is a pin or the host asked for it, and Leaflet starts when the box is on the page (watch on mapOpen), never in a hidden one. Nothing typed in the name is sent to Nominatim until one of the search actions is pressed: do not turn it into search-as-you-type (a private name like "Alice's flat" would leave the browser). A result picked with an empty name fills the name. This supersedes the older note that a picked result fills an empty Place field in EventForm.

## A host is not going until they say so
Owner's decision 2026-11-22: the host of an event answers like everyone else (also when it is hosted as an organizer). Never add the host to "Going" for display or count: the only truth is their event_attendees row, made by EventAttendanceController like anyone's. GuestFaces lists them (first, with the HOST tag) only if they answered; EventCard and the event page show the host the same answer control, beside Invite. Hosts of events made before that date have no answer and were left that way on purpose. The event form is in the order a host knows things (name, when, where, then description, cover, categories, visibility); the description is optional and stored as '' (the column is NOT NULL), and a new event redirects to events.show#invite, which opens the invite dialog and then drops the fragment.
