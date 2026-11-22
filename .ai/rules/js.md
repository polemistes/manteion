---
paths:
  - 'resources/js/**'
---

# Js

## Editable fields need v-model with local state, never a bare :value binding
Vue re-applies the `value` prop on every re-render even when it did not change (patchElement special-cases the key `value`). So an `<input :value="props.user.name">` inside a component that has any other reactive state is wiped back to the prop as soon as that state changes (a checkbox toggle, a v-if switch, a usePoll refresh). This silently lost typed profile edits and leaflet text. Pattern: `const name = ref(props.user.name)` + `v-model="name"`, and reset the refs yourself in an `@success` handler when the form uses reset-on-success. `:value` with an `@input`/`@change` handler that writes the same ref back (a controlled input) is fine, as are hidden inputs.

## Format every date with useDates() from lib/dates.ts
The dev setup renders SSR and hydrates it, so a date formatted differently on Node and in the browser is a hydration mismatch. useDates() passes the locale (en-GB/nb-NO) and the viewer's time zone (page.props.timezone: their device's as the server knows it, Europe/Oslo until it is reported) to every formatter and turns the thin and no-break spaces some ICU versions emit into plain ones. Never call toLocaleString(), Intl.DateTimeFormat or formatRange directly in a component; add a formatter to lib/dates.ts instead. A date-only value (YYYY-MM-DD) goes through noonOf() from components/events/calendar.ts before formatting, so it lands on the same day in any zone. Forms convert a wall-clock date and time with zonedToUtc()/utcToZoned().

## Build screens from the Terracotta kit, not ad hoc classes
Pages use resources/js/components/ui (Button, IconButton, TextField/TextArea with v-model, SelectField, Switch/SwitchRow, FilterChip, AppDialog, Menu, ListRow, Tag, EmptyState, SectionHeader) inside PageHeader + PageContainer, with the tokens in resources/css/app.css (bg-surface, text-ink-muted, border-line, text-title-*, rounded-lg…). The old classes (border-edge, border-frame, text-ink/70, bg-surface-raised, rounded-card) are gone. Inside a component never paint with the page's colour: a recessed area inside a surface (a link preview, a quote, an out-of-month day, the leaflet composer's pill) is `bg-recess`, and text on an ink fill (a selected chip or day, a checkbox's check, the host tag) is `text-on-ink`; both equal the canvas in Classic and Aegean, but the Taverna skin paints the canvas terracotta, where `bg-canvas`/`text-canvas` inside a card looked wrong (owner, 2026-11-01). Terracotta (accent) means you, now, or needs you (design spec §1): the one primary action in a view, unread counts and badges, today, invitations and requests, your own "Going", the active tab and the map pin — never for links, selected chips or switches. /dev/kit (local only) shows every primitive in both modes.

## A boolean prop left out is false, not undefined
Vue casts a declared boolean prop that the parent omits to false, so `props.flag !== false` can never mean "not passed". When the default must be true (EventFilterSheet's withPlaceAndDates), declare it with withDefaults({ flag: true }) and pass it through as is.

## Messages are between friends only
StoreConversationRequest rejects anyone who is not the sender's friend, so a "Message" action may only be offered for friends (People lists pass canMessage from the friends set; profiles show it when friend_status is 'friends'). Staff write to anyone through moderation.users.message instead.

## Small targets get an after: hit area, and truncate clips it
Touch targets under 44px get an invisible hit area instead of growing: `relative after:absolute after:inset-x-0 after:-inset-y-1` (see FilterChip, Button size sm, CommentThread). Size the inset so neighbours' areas don't overlap. `truncate`/`overflow-hidden` on the same element (or an ancestor) clips the ::after, so put `truncate` on an inner span (LeafletCard author link).

## A reka wrapper with as-child runs its own click before the child's
With `as-child`, reka merges its handlers in front of the child's (`mergeProps(attrs, child.props)`), and the wrapper is applied to the FIRST child. Two bugs came from that (found 2026-11-22): ConfirmDialog's yes-button sat in an AlertDialogAction, whose close reached onOpenChange() first and resolved every confirmation as "no" (nothing confirmed was ever done); and `<MenuItem as-child><Link>` put the item on the icon, not the link. So: the confirming button is a plain Button that calls resolveConfirm(true) (only Cancel keeps its wrapper), and a menu item navigates with `@select="router.visit(url)"`, never as-child. After touching a dialog or menu, click it in a browser: the tests cannot see this.

## Unsaved work asks before leaving through useLeaveGuard; popstate listeners run in the order they were added
The event form asks before it is left with changes in it (composables/useLeaveGuard.ts): links and buttons through router.on('before'), reload/close through beforeunload, and the browser's or phone's back/forward by undoing the move (history.go back to the form) before Inertia sees it. That last part only works because lib/leaveGuard.ts adds its popstate listener in app.ts before createInertiaApp(): browsers run window popstate listeners in the order added, the capture flag changes nothing there (measured in Chromium). Keep installLeaveGuard() ahead of Inertia. A page that changes its own data without the form (router.delete …) is not asked, because only GET visits are guarded.

## Bars pinned to the bottom hide while the on-screen keyboard is up (keyboard:hidden)
In the Android app the keyboard shrinks the page (Capacitor pads the WebView), so anything `fixed bottom-0` rides on top of the keyboard and covered the field being typed in; the WebView also does not scroll a field in focus into view by itself. Reproduced on the API 36 emulator 2026-11-22 (a user's report). lib/keyboard.ts (started in app.ts) reads the visual viewport, stamps data-keyboard="open" on <html> while a text field has the focus and the viewport is 150px+ lower than its tallest, scrolls that field into the free part of the screen, and sets --keyboard-inset where the keyboard slides over the page instead (phone browsers). Give every bar pinned to the bottom `keyboard:hidden` (tab bar, the event form's submit bar, the event page's answer bar), and anchor phone sheets with `bottom-(--keyboard-inset)` as AppDialog does. Test in the app on the emulator, with a real tap: `adb shell settings put secure show_ime_with_hard_keyboard 1` makes the keyboard show there.
