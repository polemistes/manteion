---
paths:
  - 'resources/js/**'
---

# Js

## Editable fields need v-model with local state, never a bare :value binding
Vue re-applies the `value` prop on every re-render even when it did not change (patchElement special-cases the key `value`). So an `<input :value="props.user.name">` inside a component that has any other reactive state is wiped back to the prop as soon as that state changes (a checkbox toggle, a v-if switch, a usePoll refresh). This silently lost typed profile edits and leaflet text. Pattern: `const name = ref(props.user.name)` + `v-model="name"`, and reset the refs yourself in an `@success` handler when the form uses reset-on-success. `:value` with an `@input`/`@change` handler that writes the same ref back (a controlled input) is fine, as are hidden inputs.

## Format every date with useDates() from lib/dates.ts
The dev setup renders SSR and hydrates it, so a date formatted differently on Node and in the browser is a hydration mismatch. useDates() passes the locale (en-GB/nb-NO) and the display time zone (page.props.timezone, Europe/Oslo) to every formatter and turns the thin and no-break spaces some ICU versions emit into plain ones. Never call toLocaleString(), Intl.DateTimeFormat or formatRange directly in a component; add a formatter to lib/dates.ts instead. A date-only value (YYYY-MM-DD) goes through noonOf() from components/events/calendar.ts before formatting, so it lands on the same day in any zone. Forms convert a wall-clock date and time with zonedToUtc()/utcToZoned().

## Build screens from the Terracotta kit, not ad hoc classes
Pages use resources/js/components/ui (Button, IconButton, TextField/TextArea with v-model, SelectField, Switch/SwitchRow, FilterChip, AppDialog, Menu, ListRow, Tag, EmptyState, SectionHeader) inside PageHeader + PageContainer, with the tokens in resources/css/app.css (bg-surface, text-ink-muted, border-line, text-title-*, rounded-lg…). The old classes (border-edge, border-frame, text-ink/70, bg-surface-raised, rounded-card) are gone. Terracotta (accent) means you, now, or needs you (design spec §1): the one primary action in a view, unread counts and badges, today, invitations and requests, your own "Going", the active tab and the map pin — never for links, selected chips or switches. /dev/kit (local only) shows every primitive in both modes.

## A boolean prop left out is false, not undefined
Vue casts a declared boolean prop that the parent omits to false, so `props.flag !== false` can never mean "not passed". When the default must be true (EventFilterSheet's withPlaceAndDates), declare it with withDefaults({ flag: true }) and pass it through as is.

## Messages are between friends only
StoreConversationRequest rejects anyone who is not the sender's friend, so a "Message" action may only be offered for friends (People lists pass canMessage from the friends set; profiles show it when friend_status is 'friends'). Staff write to anyone through moderation.users.message instead.

## Small targets get an after: hit area, and truncate clips it
Touch targets under 44px get an invisible hit area instead of growing: `relative after:absolute after:inset-x-0 after:-inset-y-1` (see FilterChip, Button size sm, CommentThread). Size the inset so neighbours' areas don't overlap. `truncate`/`overflow-hidden` on the same element (or an ancestor) clips the ::after, so put `truncate` on an inner span (LeafletCard author link).
