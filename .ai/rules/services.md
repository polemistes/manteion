---
paths:
  - app/Services/EventFeedQuery.php
  - app/Services/ActivityFeed.php
---

# Services

## Event days are display-time-zone days, and an event counts on every day it runs
Times are stored in UTC and shown in config('app.display_timezone'). "Today", date filters and calendar ranges are days in that zone, never now()->toDateString() or whereDate() (both UTC, wrong between midnight and 02:00 in Oslo). Filter with EventFeedQuery::happeningBetween($query, $from, $until): it converts the days to UTC instants and keeps an event that started earlier but has not ended (ends_at), so a weekend trip still shows on its Saturday. The frontend groups by the same zone through useDates().

## How activity is read, answered and withdrawn
ActivityFeed::for() returns each notification with a `context` (the event as the user may see it, the friend/follow request and its status, whether they follow the actor back), loaded in a handful of queries, so the Activity page can answer in place. Live broadcasts carry no context: the page reloads `notifications` when one arrives. Read state: a full load of the Activity page marks everything read (partial reloads, prefetches and impersonation do not); opening an event, leaflet or profile marks what concerns it; answering a request anywhere marks the requester's. Deleting a subject withdraws everyone's notifications about it (withdrawAbout), and deleting the thing a notification quotes withdraws that one (withdraw). Each change that can move a count dispatches BadgesUpdated.
