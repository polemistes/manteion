---
paths:
  - 'app/Notifications/**'
---

# Notifications

## Notifications are queued; production runs a real queue worker
Every notification implements ShouldQueue and mail is sent with ->queue(). Production uses QUEUE_CONNECTION=database with `php artisan queue:work --sleep=1` kept alive by the manteion-queue systemd service (deploy/push.sh restarts it after every deploy; deploy/deploy.sh warns on sync/deferred). Never send web push or mail inline to be safe. Tests use sync, so Notification::fake() still works. Activity kinds extend ActivityNotification: database + broadcast (toBroadcast on the sync connection) + WebPushChannel when $user->wantsPush(category). Their data keeps one shape (actor_id, subject_type, subject_id, subject_title, url, excerpt, pending, plus references()) so ActivityFeed can mark read or withdraw by subject. Follower fan-out lives in App\Jobs\NotifyFollowersOfEvent, guarded by events.followers_notified_at.

## The Android app is a push device too: FcmChannel beside WebPushChannel
push_subscriptions rows have a `kind` (PushKind: webpush | fcm); for fcm the endpoint is the app's FCM registration token and the keys are null. Every notification that lists WebPushChannel must also list FcmChannel; each routes only to its own kind (User::routeNotificationForWebPush/ForFcm, both honouring PushTest's single endpoint). FcmChannel sends the notification's own toWebPush() as a data-only FCM message: data.payload is that JSON as one string (FCM data values must be strings), and the app's Notifier (android/app/.../Notifier.java) reads the same shape as public/sw.js. So anything the app needs to show goes into toWebPush() data, never a second message format. Without FCM_CREDENTIALS the channel is a quiet no-op.
