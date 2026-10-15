# Manteion Android app: implementation plan

For the agent implementing it in this repository. The owner reads section 1
first and confirms the decisions there; everything after that is the brief.

## Why

The installed web app ("add to home screen") shows its notifications as coming
from Firefox or Chrome, because web push is delivered and displayed by the
browser. An Android app shows them under Manteion's own name and icon. A
WebView has no Push API at all, so the existing Capacitor shell gets no
notifications today; it needs Firebase Cloud Messaging (FCM), which is the
only push transport Android offers to apps.

The goal is notifications that work as much like Facebook's as possible, on
the phone and on the desktop, with one difference: the user decides what they
get. On the phone that means a notification with the sender's picture, a
conversation that collects its messages and can be replied to from the
notification, notifications grouped under the app, cleared when the thing is
read, opening the exact page when tapped, and a category per kind of
notification in Android's own settings. On the desktop, Facebook relies on the
browser's web push and in-page toasts and counts, which Manteion already has
(section 9).

The app stays what the shell already is: the live site at
https://www.manteion.no inside a native window. No offline copy of the SPA,
no API layer, no second login system. Web changes keep shipping without a new
app build. The app adds exactly these native things: push through FCM with
notifications built by the app itself, opening manteion.no links in the app,
a proper status bar, an offline page, and a way to install and update it.

**The user decides**: the server sends a push only for a category the user
has switched on, respects the message-preview choice and conversation mutes,
and never sends reminders, digests or "you have unread messages" nudges. The
in-app switches are the master; Android's per-category channels only tune
sound and importance on top.

## 1. Decisions for the owner

Each has a recommendation; say if you want otherwise.

1. **Push transport: FCM** (recommended). Needs a free Firebase project that
   you create (section 7). Chrome's web push already goes through Google's
   push service, so this adds no party that isn't already involved. The only
   Google-free alternative, UnifiedPush, needs every tester to install and
   configure a distributor app (ntfy), which is more friction than the
   problem we are solving.
2. **The app builds its notifications itself** (recommended). The server
   sends data-only FCM messages carrying the same JSON the service worker
   gets today, and a small native messaging service in the app turns them
   into Facebook-style notifications. The alternative, FCM "notification
   messages" that Android displays on its own, cannot show the sender's
   picture, collect a conversation's messages, offer a reply, or clear on
   read, so it would have to be replaced later anyway.
3. **Dependencies**: one Composer package, `google/auth` (fetches the OAuth
   token FCM wants from a service-account file). No npm push plugin: the
   native side is a local Capacitor plugin inside `android/`, with Firebase
   Messaging added through Gradle. The alternative
   `laravel-notification-channels/fcm` pulls in the whole Kreait Firebase SDK
   for the one call we make; not worth it.
4. **Distribution: a signed APK on GitHub Releases** (recommended now that
   the repository is public), linked from a "Get the app" page on the site,
   with an in-app "new version" notice. Google Play comes later if wanted:
   it needs a developer account (one-off fee, identity verification) and, for
   a new personal account, a closed test with 12 testers for 14 days before
   the app can be published. F-Droid is out because FCM is proprietary.
5. **Notifications on by default in the app**: on the first signed-in start
   the app asks for notification permission once (Android's own prompt), and
   registers the device if allowed. Settings → Notifications keeps working
   exactly as in a browser (this device on/off, send a test, device list).
6. **Foreground behaviour, the same rule as the web**: a push is shown unless
   the user is looking at its very page, and a test push always shows. (A
   notification is also what keeps Android from throttling the app's
   high-priority pushes, which it does to apps whose pushes show nothing.)
7. **Web push stays** for browsers and the home-screen web app, unchanged.

## 2. What is already in place

- **Capacitor 8 shell**: `capacitor.config.ts` (appId `no.manteion.app`,
  `server.url` = https://www.manteion.no, `webDir` = `public/build`),
  `android/` and `ios/` committed, `MainActivity.java` is a bare
  `BridgeActivity`. `resources/js/native.ts` sets the status bar and maps the
  back button; `resources/js/pwa/platform.ts` has `isNativeShell()`, and
  `pwa/push.ts` reports push as `unsupported` inside the shell. Plugins
  installed: `@capacitor/app`, `splash-screen`, `status-bar`. Icons and
  splashes were generated from `mobile-assets/logo.png`.
- **CI**: `.github/workflows/mobile-android.yml` builds a debug APK on every
  push to main that touches `android/`, `resources/` and friends; it succeeds
  in about three minutes. `android/app/build.gradle` already applies the
  Google services plugin when `android/app/google-services.json` exists, and
  `android/build.gradle` has its classpath. The repository is public, so
  Actions minutes are free and GitHub Releases can serve downloads.
- **Laptop toolchain**: Android Studio in `/opt/android-studio` (its JDK at
  `/opt/android-studio/jbr`; the system `java` is a bare runtime, so set
  `JAVA_HOME=/opt/android-studio/jbr` for Gradle), SDK in
  `~/Android/Sdk` (platform 36, build-tools 36/37, `adb` on PATH), and two
  AVDs: `anagnosis` (API 36.1, `google_apis` image, so Google Play services
  and FCM work in it; no Play Store) and `tablet`. There is no
  `android/local.properties` yet; Gradle needs one with `sdk.dir`, or
  `ANDROID_HOME` set.
- **Web push**: `laravel-notification-channels/webpush`, VAPID keys in the
  env. `App\Models\PushSubscription` extends the package model and adds
  `user_agent`; one row is one device. `PushSubscriptionController` has
  `store` (by endpoint, validated by `App\Rules\PushServiceEndpoint`, keeps
  the newest `MAX_DEVICES` = 10), `destroy` (by endpoint) and `destroyDevice`
  (by id, from the settings page). `User::routeNotificationForWebPush()`
  returns all the user's subscriptions, or only the asking one for a
  `PushTest`. `App\Services\DeviceLabel` names a device from its user agent.
- **Notifications**: `ActivityNotification` (database + broadcast +
  `WebPushChannel` when `wantsPush(category)`), `NewMessageReceived` (push
  only, `shouldSend` re-checks mute and presence), `PushTest` (sent at once,
  to one endpoint). Every `toWebPush()` returns title, body, icon, badge,
  tag, renotify, lang, `data` (`url`, `type`, `badge_count`, message ids) and
  options (`TTL`, `urgency`, `topic` for messages). All of them are queued;
  production runs `manteion-queue`. Categories are the
  `App\Enums\NotificationCategory` enum (nine of them, grouped on the settings
  page as messages, events, people, leaflets).
- **Settings → Notifications** (`resources/js/pages/settings/Notifications.vue`,
  `Settings\NotificationController`): a "this device" switch that calls
  `subscribe()`/`unsubscribe()` from `pwa/push.ts`, "Send a test" that waits
  for the service worker to report a push of type `test` through
  `onPushMessage()`, and a device list that recognises the current device by
  `sha256(endpoint)`.
- **Service worker** `public/sw.js`: shows pushes, folds messages of one
  conversation ("3 new messages"), skips a push for the page in front, opens
  the page on tap, and closes a conversation's notifications when the app
  opens that conversation (`clearNotifications(tag)` in
  `realtime/conversation.ts`). None of it runs in the app; the native
  messaging service takes over those jobs with the same payload.
- **In-page, on every platform**: incoming messages show a banner and play a
  sound (`AppLayout.vue`, `lib/toast.ts`), badges update live over Reverb,
  the tab title gets "(3)" and the favicon a dot, and the Activity page
  prepends new items as they arrive.
- **Sessions**: cookie based, "remember me" ticked by default on the login
  page, so the WebView stays signed in. Reverb WebSockets are same origin and
  work in the WebView already. A message is sent with `POST
  /messages/{conversation}/send` and one field, `body`; a conversation is
  marked read with `POST /messages/{conversation}/read`. Both answer JSON to
  a JSON request.
- **Rules to read first**: `.ai/rules/index.md`, then `notifications.md`
  (everything queued, never inline), `tests.md`, `js.md`, `lang.md`,
  `js-lang.md`. `CLAUDE.md` applies throughout (Pint, Pest, Wayfinder, no new
  base folders, no dependency changes beyond those approved above).

## 3. Design

**A device is a row in `push_subscriptions`**, web push or FCM alike. A new
`kind` column (`webpush` | `fcm`) tells them apart. For an FCM device the
`endpoint` holds the FCM registration token and the key columns are null.
Everything built on "devices" keeps working unchanged: the ten-device limit,
removal by id from the settings page, the "this device" match by endpoint
hash, `DeviceLabel`, and the test push to one endpoint.

**Sending**: a new `FcmChannel` sits beside `WebPushChannel`. Every
notification that lists `WebPushChannel` lists `FcmChannel` too; each channel
routes only to its own kind of device, so a user with no app gets nothing
from FCM and a user with no browser subscription gets nothing from web push.
The FCM message is data-only and carries the notification's existing
`toWebPush()` message as JSON, so no notification class needs a second
message body, and the app and the service worker read one payload format.

**Showing**: a native `FirebaseMessagingService` in the app builds the
notification: `MessagingStyle` with the sender's picture for messages, the
actor's picture as the large icon for activity, grouped under the app with
one summary per group, a channel per Manteion category, reply and mark-as-
read actions on conversations, and the same "not when you are looking at
it" rule as the service worker. A tap opens the app on `data.url`. Opening a
conversation in the app clears its notification, as on the web.

**Registering**: inside the shell, `pwa/push.ts` delegates to a native
implementation that asks permission, fetches the FCM token through the local
plugin and posts it as a device of kind `fcm`. The settings page does not
change.

## 4. Phase 1: server side, FCM devices and channel

Deployable on its own: with no app yet there are no FCM rows and nothing is
sent. Until the owner has put the credentials on the server, the channel must
be a quiet no-op (log once at debug level), the same way the settings page
says "unconfigured" when no VAPID key is set.

1. **Migration**: add `kind` (string, default `webpush`) to
   `push_subscriptions`, through `config('webpush.table_name')` like the
   existing migrations. Add a backed enum `App\Enums\PushKind` (`WebPush`,
   `Fcm`), cast on the model, and query scopes `webPush()` and `fcm()`.
2. **Routing**: `User::routeNotificationForWebPush()` returns only web push
   rows (keep the `PushTest` filter); add `routeNotificationForFcm()` with
   the same shape for FCM rows.
3. **Registering an FCM device**: `PushSubscriptionController@store` accepts
   `kind` (default `webpush`). For `fcm`: `endpoint` is the token (validate
   with a pattern such as `^[A-Za-z0-9_:\-]{20,512}$`, no URL rule),
   `key`/`token`/`content_encoding` must be absent, and the row is created or
   refreshed by endpoint for that user with `kind` = `fcm` (don't route this
   through the package's `updatePushSubscription()`, which assumes web push
   fields). Keep the `MAX_DEVICES` pruning for both kinds. `destroy` by
   endpoint already fits.
4. **Config**: `config/services.php` gets
   `fcm.project_id` (`FCM_PROJECT_ID`) and `fcm.credentials`
   (`FCM_CREDENTIALS`, absolute path of the service-account JSON). Add both
   to `.env.example` and `deploy/manteion.env.example` with a comment, and a
   paragraph in `deploy/README.md` saying where the file lives on the server
   (`/home/manteion/firebase-service-account.json`, owned by `manteion`,
   mode 600, outside the release directories) and that the queue worker must
   be restarted after adding it (a deploy does that).
5. **`App\Services\Fcm`** (or `Fcm\Client`): `configured(): bool`; an access
   token from `Google\Auth\Credentials\ServiceAccountCredentials` with scope
   `https://www.googleapis.com/auth/firebase.messaging`, cached in the
   Laravel cache for a little less than its lifetime; `send(string $token,
   array $message)` posting to
   `https://fcm.googleapis.com/v1/projects/{project}/messages:send` with
   `Http::withToken()`. Make the token source swappable (a small interface or
   a closure bound in the container) so tests never touch Google. Result
   handling: HTTP 404 with error code `UNREGISTERED`, or 400
   `INVALID_ARGUMENT` about the token, means the device is gone: delete the
   row. 401 once: drop the cached token and retry. Anything else: log with
   the status and body and move on to the next device; one dead device must
   not stop the others, and a thrown exception would fail the whole queued
   notification (the worker runs with the default single try).
6. **Payload**: add `category` (the `NotificationCategory` value) to the
   `data` of every `toWebPush()` (`ActivityNotification` from
   `static::category()`, `NewMessageReceived` from `$this->category()`;
   `PushTest` uses `direct_messages`), so the app can pick the channel. Give
   `NewMessageReceived` the structured fields a conversation-style
   notification needs, beside the title and body it already builds:
   `sender_name`, `sender_avatar` (absolute URL or null), `conversation_title`,
   `conversation_type` (`direct`/`group`) and `text` (null when the user has
   previews off). The service worker ignores keys it doesn't know, so web
   push is unaffected. `icon` may be site-relative today; the app resolves it
   against the site origin.
7. **`App\Notifications\Channels\FcmChannel`** and
   `App\Notifications\Messages\FcmMessage`: `FcmMessage::fromWebPush(
   WebPushMessage $message)` reads `toArray()` and `getOptions()` and builds
   a data-only message:
   - `data.payload`: the `toArray()` result as a JSON string (FCM data values
     must be strings; one key keeps the format identical to the web push
     body). Stay well under FCM's 4 KB limit; the bodies are already capped.
   - `android.priority`: `high` for everything (a data message of normal
     priority can wait until the phone wakes up); `android.ttl`: `"{TTL}s"`;
     `android.collapse_key`: the `topic` when set, so an offline phone gets
     the latest message of a conversation rather than a pile, as web push
     does.
   `send($notifiable, $notification)`: skip when not configured or when
   `routeNotificationForFcm()` is empty, else build the message from
   `$notification->toWebPush($notifiable, $notification)` and send to each
   device. Laravel already calls `shouldSend()` per channel, so
   `NewMessageReceived`'s presence and mute checks apply unchanged.
8. **`via()`**: `ActivityNotification`, `NewMessageReceived` and `PushTest`
   add `FcmChannel::class` wherever they list `WebPushChannel::class`.
9. **Device label**: the app appends `Manteion-Android` to the WebView's user
   agent (phase 2); add a first entry to `DeviceLabel::BROWSERS` mapping it to
   "Manteion app", so the settings page shows "Manteion app on Android"
   (`lang/en/devices.php` and `lang/nb/devices.php` already have
   `browser_on_platform`).
10. **Shared prop**: `HandleInertiaRequests` shares `fcmConfigured` (bool),
    so the settings page inside the app can show the existing "unconfigured"
    text when the server has no FCM project yet.
11. **Tests** (Pest, feature, `RefreshDatabase` as the suite does):
    - `PushSubscriptionTest`: registering an FCM device stores the token with
      kind `fcm` and the user agent; a malformed token is rejected; web push
      fields are refused for kind `fcm`; the ten-device limit counts both
      kinds; destroy by endpoint removes an FCM device.
    - New `FcmChannelTest`: `Http::fake()`; a `NewMessageReceived` to a user
      with one web push and one FCM device sends exactly one FCM request with
      the expected token, `collapse_key`, high priority and a `payload` whose
      decoded JSON has the title, body, tag, `category`, sender fields and
      `url`; an `ActivityNotification` subclass carries its category;
      `UNREGISTERED` deletes the device; when FCM is not configured nothing is
      sent and nothing throws; `routeNotificationForWebPush()` excludes FCM
      rows; `PushTest` reaches only the asking device of either kind.
    - `DeviceLabel` test for the app's user agent.

## 5. Phase 2: the app registers with FCM and shows notifications

The native code lives in the app project (`android/app/src/main/java/no/
manteion/app/`), as a local Capacitor plugin plus a messaging service.
Kotlin is preferred (add the Kotlin Gradle plugin); Java is acceptable if
that is less fuss in this project. Firebase comes in through Gradle: the
Firebase BoM and `firebase-messaging` in `android/app/build.gradle`; the
Google services plugin is already applied when `google-services.json` is
present.

1. **Secrets and CI**: add `android/app/google-services.json` to
   `android/.gitignore` (it holds a Firebase API key; not a secret in
   Google's eyes, but keep it out of a public repository) and have CI write
   it from a secret `GOOGLE_SERVICES_JSON` before the Gradle step. Locally the
   owner drops the file in place.
2. **Local plugin `ManteionPush`** (`@CapacitorPlugin`, registered in
   `MainActivity.onCreate` before `super.onCreate`; the JS side gets it with
   `registerPlugin('ManteionPush')` from `@capacitor/core`, with a typed
   interface in `resources/js/native/`). Methods: `checkPermissions` and
   `requestPermissions` for `POST_NOTIFICATIONS` (Android 13+; granted
   implicitly below), `getToken` (Firebase Messaging's token), `deleteToken`,
   `createChannels` (takes the list of channels with ids, names, groups and
   importance; creates or updates them), `cancel` (by tag, or all),
   `setCurrentPath` (the page in front, for the foreground rule). Events:
   `token` (a refreshed token from `onNewToken`), `received` (a push that
   arrived while the app is in the foreground, with its `data`), `tapped`
   (the `url` of a tapped notification; emitted with retain-until-consumed so
   a cold start delivers it once a listener exists).
3. **Channels**: one per `NotificationCategory`, ids equal to the enum values,
   in four channel groups matching the settings page (messages, events,
   people, leaflets). `direct_messages` and `group_messages` get importance
   high (heads-up, sound, vibration); the others default. Names come from
   the JS lang files, passed at startup, because Android shows them in its
   settings. The in-app switches stay the master: the server sends nothing
   for a category that is off, whatever the channel says.
4. **`ManteionMessagingService`** (`FirebaseMessagingService`, declared in the
   manifest with the `com.google.firebase.MESSAGING_EVENT` intent filter,
   not exported). `onNewToken`: save the token in the app's preferences and
   emit `token` if the plugin is alive; the JS side re-posts it. `onMessageReceived`:
   parse `data.payload` and hand it to a notification builder:
   - **Foreground rule**: track whether the app is in the foreground
     (`ProcessLifecycleOwner` from `androidx.lifecycle:lifecycle-process`, or
     the activity's lifecycle) and the current path. If in the foreground,
     emit `received` to JS. Then show the notification unless the app is in
     the foreground on the push's own path; `type` = `test` always shows.
   - **Messages** (`type` = `message`): `NotificationCompat.MessagingStyle`
     with a `Person` per sender (name, circular avatar downloaded with a short
     timeout and cached by URL in the cache dir; app icon as fallback), the
     conversation title and `setGroupConversation(true)` for groups, the
     messages of that conversation collected (keep the recent ones per tag in
     preferences, like the service worker's count; drop them on cancel, tap or
     mark-as-read). With previews off, a plain notification with the existing
     title and body and no text. Tag = `conversation-{id}`, `setWhen` from
     the payload's timestamp, `setNumber(badge_count)`, category
     `CATEGORY_MESSAGE`, group `conversations` with a summary notification
     (`InboxStyle`), auto-cancel. Actions: **Reply** (a `RemoteInput`
     delivered to a `BroadcastReceiver` that posts `body` to
     `/messages/{conversation_id}/send` and appends the reply to the
     notification on success) and **Mark as read** (posts to
     `/messages/{conversation_id}/read` and cancels). Both requests send the
     WebView's cookies (`CookieManager.getInstance().getCookie(origin)`) with
     `X-XSRF-TOKEN` set to the URL-decoded `XSRF-TOKEN` cookie, `Accept:
     application/json` and `X-Requested-With: XMLHttpRequest`; a 401 or 419
     means the session is gone, so open the app on the conversation instead.
   - **Activity** (everything else): `BigTextStyle` with the payload's title
     and body, the actor's picture as the large icon, tag from the payload
     (so a newer notification about the same subject replaces the older one,
     as on the web), `setNumber`, category `CATEGORY_SOCIAL`, group
     `activity` with a summary, auto-cancel.
   - **Tap**: a `PendingIntent` to `MainActivity` (launch mode `singleTask`
     already) with the `url` as an extra; the plugin's `handleOnNewIntent`
     and `load` turn it into the `tapped` event.
   - Small icon `ic_stat_manteion`, a white-on-transparent monochrome of the
     tripod logo (vector drawable or PNGs per density); accent colour
     `#C23132`. Lock screen visibility follows the channel; a public version
     for messages with previews off is not needed since they carry no text.
5. **`capacitor.config.ts`**: `android.appendUserAgent: 'Manteion-Android'`;
   keep `server.url`. Offline page: point `webDir` at a small folder holding
   one `index.html` (a copy of `public/offline.html`, which is self-contained,
   plus a "Try again" button that navigates to
   `https://www.manteion.no/events`), and set `server.errorPath` to it, so a
   failed load shows Manteion's page instead of the WebView's error. Keep
   that folder under `resources/` (for example `resources/native-shell/`) and
   update the CI workflow's path filter.
6. **`resources/js/native/push.ts`**, the shell's counterpart of
   `pwa/push.ts`, with the same exported shapes: `pushSupport()` returns
   `supported` (`unconfigured` is decided by the page from `fcmConfigured`);
   `permission()` maps the plugin's permission state to the web values;
   `subscribe()` requests permission, calls `getToken`, posts `{ kind: 'fcm',
   endpoint: token }` to the existing `push-subscriptions.store` route through
   `requestJson()` and keeps the token in `localStorage` (the WebView's
   storage persists and is the site's origin); `unsubscribe()` deletes by
   endpoint then calls `deleteToken`; `currentEndpoint()` returns the stored
   token; `forgetSubscription()` sends a best-effort delete while the session
   still exists, then `deleteToken` (an invalid token makes the next push
   return `UNREGISTERED`, and the server drops the row). Make `pwa/push.ts`
   delegate to this module when `isNativeShell()` is true, so
   `Notifications.vue` needs no change beyond reading `fcmConfigured`.
7. **Startup and listeners** (`native.ts`, or a `native/` folder): on every
   native start, `createChannels`; on each Inertia `navigate`,
   `setCurrentPath`. With a signed-in user whose device was registered
   before, call `getToken` again and re-post when it differs; do the same on
   the `token` event. Feed `received` to the listeners `onPushMessage()`
   serves (export a dispatch function from `pwa/serviceWorker.ts`), so "Send
   a test" reports arrival as on the web. On `tapped`, visit the URL with the
   Inertia router after a same-origin check. Make `clearNotifications(tag)`
   in `pwa/serviceWorker.ts` call the plugin's `cancel` in the shell, so
   opening a conversation clears its notification as on the web, and make
   `setAppBadge` a no-op there. On `App` `resume`, call `refreshBadges()`
   from `@/realtime`.
8. **First-run prompt** (decision 5): after the first signed-in page load in
   the shell, if permission is `prompt` and nothing is stored, call
   `subscribe()` once from a small in-app sheet (one sentence of explanation
   and a button, built from the Terracotta kit), and remember that it was
   asked. A denied answer leaves it to Settings, which already explains how
   to unblock.
9. **Logout** (`realtime/index.ts` already intercepts the logout visit): for
   the shell, call the native `forgetSubscription()` in the `before` hook
   rather than after success, so the delete request goes out while the
   session is alive.
10. **Status bar and insets**: `native.ts` currently paints the status bar
    black with light icons, which clashes with the light canvas. Set the
    style from the page's theme (`#F7F3EC` with dark icons for light,
    `#14100D` with light icons for dark) and update it when `applyTheme()`
    runs. With `targetSdkVersion` 36 the system draws edge to edge on Android
    15+; check in the API 36 emulator that the header and bottom bar clear
    the status and navigation bars, and pad with `env(safe-area-inset-*)` in
    the shell's layout where they don't (`viewport-fit=cover` is already
    set).
11. **Settings → Index**: inside the shell, hide the "Install" card (it uses
    `useInstallPrompt()`, which never fires in a WebView, so it is probably
    hidden already; verify).
12. **Verify in the emulator** (`anagnosis` AVD; FCM works in `google_apis`
    images): build with `JAVA_HOME=/opt/android-studio/jbr`, `npm run build`,
    `npx cap sync android`, `cd android && ./gradlew assembleDebug`, install
    with `adb install -r`. Check: sign in, permission prompt, device appears
    in Settings as "Manteion app on Android", "Send a test" shows a
    notification under Manteion's name and the page says it arrived, a
    message from another account (a second browser on the laptop) shows a
    conversation notification with the sender's picture while the app is
    closed, a second message joins it, Reply sends and shows in the chat, Mark
    as read clears it and the badge, tapping opens the conversation, opening
    the conversation in the app clears the notification, a comment on an event
    (with that category on) shows an activity notification with the actor's
    picture, two of them group under one summary, the channels appear in
    Android's app settings under their groups, turning the device off removes
    it, logging out stops pushes, aeroplane mode shows the offline page and
    "Try again" recovers, external links open in the system browser
    (Capacitor's default for other hosts), and the status bar matches the
    theme in both modes.

There is no JavaScript test runner in this project; the Vue side is verified
in the emulator and the server side in Pest. Keep `vue-tsc`, ESLint and
Prettier green (`composer ci:check`). Native unit tests are not expected;
keep the Kotlin small and verified by the emulator checklist.

## 6. Phase 3: signing, release build, download and update notice

1. **Keystore**: the owner generates one (section 7) and keeps it out of the
   repository. `android/app/build.gradle` gets a `signingConfigs.release`
   that reads `MANTEION_KEYSTORE_FILE`, `MANTEION_KEYSTORE_PASSWORD`,
   `MANTEION_KEY_ALIAS` and `MANTEION_KEY_PASSWORD` from the environment and
   is only applied when they are set, so debug builds and CI without secrets
   keep working.
2. **Versions**: `versionCode` and `versionName` live in
   `android/app/build.gradle` (now 1 / 1.0). Bump both for every release
   (`versionCode` must always increase; keep `versionName` numeric and
   increasing, like 1.1, 1.2, 1.10); start at 2 / 1.1.
3. **Release job**: a `release` job in `mobile-android.yml`, triggered by
   tags `android-v<versionName>` (and `workflow_dispatch`), which writes the
   keystore (base64 secret) and `google-services.json` from secrets, builds
   `assembleRelease`, and creates a GitHub Release for the tag with the APK
   attached as `manteion.apk`. The stable link
   `https://github.com/polemistes/manteion/releases/latest/download/manteion.apk`
   then always points at the newest build. Local release builds work the
   same way with the four variables exported.
4. **"Get the app" page**: a public Inertia page at `/app` (route
   `app.android`) with the download link, the three steps Android asks for
   when installing an APK from outside the store, and what the app does
   better than the home-screen web app. Link it from the Settings index on
   Android browsers (beside the existing install card) and from the Welcome
   page's footer. Texts in both languages.
5. **Update notice**: inside the shell, once a day, fetch
   `https://api.github.com/repos/polemistes/manteion/releases/latest` (the
   API allows cross-origin reads without a token; one call per device per
   day is far inside its limits), compare the tag's version with
   `App.getInfo().version` segment by segment, and when newer show a
   dismissable banner (Terracotta kit) linking to the download. No forced
   updates.
6. **Tests**: the `/app` page renders for guests and users.

## 7. What the owner does, and what the agent does for them

Only two things need the owner personally, because they need their Google
account or their password manager. The agent gives them as explained steps,
like `deploy/README.md` does, when the phase needs them, and does the
mechanical parts itself once the owner hands over the files.

1. **Firebase** (before phase 1 can send and phase 2 can build; about twenty
   minutes; free, no billing account). The owner, in the Firebase console:
   create a project "Manteion" without Google Analytics (push does not
   need it, the app includes no Analytics library, and the site promises no
   tracking); add an Android app with package name
   `no.manteion.app`; download `google-services.json`; in Project settings →
   Cloud Messaging make sure the "Firebase Cloud Messaging API (V1)" is
   enabled; in Service accounts generate a private key (JSON). Then tell the
   agent where the two files are on the laptop. The agent: puts
   `google-services.json` in `android/app/`, adds it as the CI secret
   `GOOGLE_SERVICES_JSON` with `gh secret set`, copies the service-account
   key to the server as `/home/manteion/firebase-service-account.json`
   (owner `manteion`, mode 600, as the manteion user through the existing
   sudoers rule) and adds `FCM_PROJECT_ID` and `FCM_CREDENTIALS` to
   `/home/manteion/manteion.env`. The next deploy restarts the queue worker.
2. **Keystore** (at the first release build, phase 3; five minutes). The
   agent generates it with `keytool -genkeypair -v -keystore
   manteion-release.jks -alias manteion -keyalg RSA -keysize 2048 -validity
   10000` (keytool is in `/opt/android-studio/jbr/bin`) using passwords the
   owner chooses, outside any repository. The owner keeps the file and the
   passwords in their password manager with a backup: losing it means never
   being able to update the installed app. The agent adds the file
   (base64) and the passwords as CI secrets.
3. **Apache** (phase 4): only if the check shows that
   `/.well-known/assetlinks.json` is not served, which Laravel's public
   folder normally handles without any Apache change.
4. **Google Play** (later, only if wanted): developer account and the closed
   test.

## 8. Phase 4: open manteion.no links in the app

1. `public/.well-known/assetlinks.json` with the package name and the
   release certificate's SHA-256 fingerprint (`keytool -list -v -keystore
   … | grep SHA256`). Add the debug certificate's fingerprint too while
   testing, and remove it before shipping.
2. In the manifest, an intent filter with `android:autoVerify="true"` for
   `https` on `www.manteion.no` and `manteion.no`.
3. `App.addListener('appUrlOpen')` → Inertia visit to the path when the
   host is ours; otherwise ignore.
4. Verify with `adb shell pm get-app-links no.manteion.app` and by tapping a
   password-reset link in an email on the phone.

## 9. Desktop: what "like Facebook" means there, and what is left

Facebook has no desktop app apart from Messenger; on a computer its
notifications are the browser's web push plus in-page toasts, counts and a
sound. Manteion already has that: web push with the sender's picture, the
banner and sound for messages, live badges, the "(3)" in the tab title and
the dot on the favicon. Chrome and Edge show an installed web app's
notifications under the app's name on Windows and macOS; Firefox, and every
browser on Linux, show the browser's name, for Facebook as much as for
Manteion. Phase 1 serves both platforms, and nothing in this plan changes
the desktop.

Small desktop follow-ups, outside this plan, if the owner wants them:

- An in-page toast for new activity items (a comment, an invitation), the
  way messages already get a banner: `onActivity()` in `AppLayout.vue` with
  the existing toast component.
- A notifications dropdown from the Activity badge, in addition to the page.
- Aggregation in Activity and in pushes ("A, B and 3 others commented"),
  which is server-side work in `ActivityFeed` and benefits phone and desktop
  alike.

## 10. Working rules for the implementing agent

- Follow `.ai/rules` (read the index and every file whose globs match), run
  `vendor/bin/pint --dirty --format agent` on PHP changes, write Pest tests
  for every server change, keep `composer ci:check` green, and commit after
  each completed step with a message in the style of the log. Do not deploy
  without the owner's go, and never deploy phase 1 before the channel's
  "not configured" path is tested, since the server will lack credentials at
  first.
- Record durable, non-obvious findings with the Boost `record-rule` tool
  (likely candidates: the JDK path for Gradle, how FCM devices share the
  push subscriptions table, that FCM `data` values must be strings and the
  payload travels as one JSON string, the foreground rule and why).
- Don't change `android/app/capacitor.build.gradle` or anything `cap sync`
  regenerates by hand; change the source (`capacitor.config.ts`, plugins) and
  sync.
- Keep the two kinds of device symmetrical: anything added for FCM devices
  (labels, limits, removal, tests) must still hold for web push ones.
- Keep the one payload format: whatever the native builder needs must be
  added to `toWebPush()` data, where the service worker can use it too.
- Phase order: 1 (server, deploy when the owner has the credentials), 2 (app,
  debug build to the owner's phone through `adb` or the CI artifact), 3
  (signed release), 4 (App Links). Phase 1 and 2 can be built together on
  the laptop before anything is deployed.

## 11. Later, if wanted

- Accept and decline actions on friend, follow and organizer requests from
  the notification (the payload's `references()` ids and the existing
  routes make it possible, with the same cookie-based request as Reply).
- Conversation shortcuts and bubbles (Android 11+ "conversations" section).
- iOS through APNs (the FCM project can relay to it) once an Apple developer
  account exists; `mobile-ios.yml` already builds a simulator app, and
  Actions minutes are free now that the repository is public.
- Google Play listing.
