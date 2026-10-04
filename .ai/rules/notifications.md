---
paths:
  - 'app/Notifications/**'
---

# Notifications

## Notifications are queued; production runs a real queue worker
Every notification implements ShouldQueue and mail is sent with ->queue(). Production uses QUEUE_CONNECTION=database with `php artisan queue:work --sleep=1` kept alive by systemd/Supervisor (deploy/deploy.sh runs queue:restart and warns on sync/deferred). Never send web push or mail inline to be safe. Tests use sync, so Notification::fake() still works. Activity kinds extend ActivityNotification: database + broadcast (toBroadcast on the sync connection) + WebPushChannel when $user->wantsPush(category). Their data keeps one shape (actor_id, subject_type, subject_id, subject_title, url, excerpt, pending, plus references()) so ActivityFeed can mark read or withdraw by subject. Follower fan-out lives in App\Jobs\NotifyFollowersOfEvent, guarded by events.followers_notified_at.
