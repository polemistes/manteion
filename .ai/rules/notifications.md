---
paths:
  - 'app/Notifications/**'
---

# Notifications

## Notifications and mail are queued; production runs the worker-less deferred queue
Every notification implements ShouldQueue and mailables are sent with ->queue(). The shared-hosting deploy has no queue worker, so production must set QUEUE_CONNECTION=deferred (jobs run right after the response is sent). Never send inline "to be safe": inline web-push and mail HTTP calls were the original problem. In tests QUEUE_CONNECTION=sync, so Notification::fake()/Mail::assertQueued() work unchanged. Follower fan-out lives in App\Jobs\NotifyFollowersOfEvent and is guarded by events.followers_notified_at so it never runs twice.
