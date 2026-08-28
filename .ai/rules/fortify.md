---
paths:
  - 'app/Actions/Fortify/**'
---

# Fortify

## A custom Fortify auth pipe after AttemptToAuthenticate must log the user back out before rejecting
Fortify's `AttemptToAuthenticate::handle()` already calls `$guard->attempt()` (which logs the user in) before invoking `$next($request)`. A custom pipe inserted after it (registered via `Fortify::authenticateThrough()`, e.g. `RejectSuspendedUsers`) runs against an *already-authenticated* session — throwing a `ValidationException` alone leaves the user logged in. The pipe must inject `StatefulGuard`, call `$this->guard->logout()` and `$request->session()->invalidate()`, then throw.
