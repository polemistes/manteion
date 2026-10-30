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
All signalling goes POST messages.call → CallSignal broadcast on every participant's own channel (sender's tabs included, each tab ignores its own tabId; to_tab addresses one tab once answered). Nothing is stored. Camera/mic state is sent as an explicit `media` signal: a sender's replaceTrack(null) does not reliably fire `mute` on the remote track in Chromium, so don't rely on track mute events. The native apps are gated out (callsAvailable) until they declare camera/microphone permissions.
