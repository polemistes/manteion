---
paths:
  - app/Http/Controllers/EventController.php
---

# Controllers

## Event lists must stay in SQL via EventFeedQuery
The events index and search build one paginated query through App\Services\EventFeedQuery (visibility union, tag/owner/group/date filters, nearest-first ordering). Do not reintroduce collection filtering after ->get(): the earlier version loaded every event the viewer had ever seen and filtered in PHP, and the search tag filter ran after the SQL limit and dropped matches. Pages send `events` (summaries) plus a separate `pagination` prop, rendered by resources/js/components/Pagination.vue.
