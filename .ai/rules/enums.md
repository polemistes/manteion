---
paths:
  - app/Enums/EventTimeZoneMode.php
---

# Enums

## An event's time zone is automatic, chosen, or none; payloads carry `local_timezone` for showing
events.timezone_mode (EventTimeZoneMode): `auto` follows the pin (or the host's zone without one), `chosen` is the host's pick and is never replaced by the pin's zone, `none` is an event with no local time (an online one). events.timezone always holds a real zone, also for `none` (the host's own): it is what the times were typed in and whose days decide when an event without an end is over, so client isOver() and Event::isOver() agree. For display use `local_timezone` (Event::shownLocalTimeZone(), null for `none`) with dates.elsewhere()/LocalTime; never `timezone`, or an online event grows a "local time". The form's picker (EventForm, "Change" under the times) lists the browser's zones (Intl.supportedValuesOf) by city; an option's label must not change after it is chosen, because the select keeps showing the old text.
