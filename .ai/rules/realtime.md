---
paths:
  - 'resources/js/realtime/**'
  - resources/js/realtime/calls.ts
---

# Realtime

## Nothing in the browser tab may change except the favicon dot
The owner finds a changing tab very annoying (in Firefox an alternating or counting title looks like the tab scrolling). attention.ts only shows a red dot on the favicon and the installed app's icon badge while something is unread. Don't put a count in document.title, alternate it with a sender, or otherwise animate the tab.

## Video calls are peer-to-peer only, signalled through the server
Decided 2026-10-31: calls run WebRTC browser-to-browser with STUN only (config/calls.php, CALLS_STUN_SERVERS). No TURN relay, ever, on the home server — its data plan is capped; a failed direct connection shows the explained "Couldn't connect" card instead. Group calls are out of scope until there is an SFU elsewhere.
All signalling goes POST messages.call → CallSignal broadcast on every participant's own channel (sender's tabs included, each tab ignores its own tabId; to_tab addresses one tab once answered). Only the ring is kept (App\Services\CallRings, cache, 50 s) so a page that opens late — from the push, after a reload, the app brought back — can fetch it (GET calls.ringing, checkPendingRing on every user-channel subscription and visibilitychange); accept/decline/busy/cancel settle it. Pushes: CallNotification kinds call → missed_call | call_over under one tag `call-<id>`, category video_calls. Camera/mic state is sent as an explicit `media` signal: a sender's replaceTrack(null) does not reliably fire `mute` on the remote track in Chromium, so don't rely on track mute events. The native apps are gated out (callsAvailable) until they declare camera/microphone permissions.

## Call rings play on demand, ignore the message-sound switch, and never read AudioContext.state
A call rings like a phone: startTone() in calls.ts does not follow the message-sound switch (that switch is "a sound for new messages"). Web Audio starts on demand: ensureAudio() returns the context whatever its state, and the tone plays from `context.resume().then(...)` — Firefox reports a context it has just made as "suspended" for ~50 ms even when it may run (the old `state === 'running'` check skipped every first burst), and a browser that blocks autoplay keeps resume() pending until the first tap; the toneTicket counter lets only the newest burst still due play then. In the Android app (1.5+) the ring itself is native (ManteionShell.startRinging(), ringtone at the ring volume), with the page's tone as fallback for older apps; the page rings only on the conversation's own page while visible (ringsInPage), the notification everywhere else. Tested with Playwright: scratchpad e2e/calls.cjs counts OscillatorNode.start calls; ring-firefox.cjs runs Playwright's Firefox (its autoplay prefs can't be changed, so blocking is acted out by patching resume()).
