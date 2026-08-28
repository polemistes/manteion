---
paths:
  - 'resources/js/pages/**'
---

# Pages

## This app hydrates real SSR output in dev — never format dates with a bare toLocaleString()
Despite no `resources/js/ssr.ts`, this project's dev setup does render and hydrate SSR output (confirmed via live Vue "Hydration completed but contains mismatches" warnings). `new Date(x).toLocaleString()` with no explicit locale/options renders differently on the Node SSR side vs the browser (different default locale/timezone formatting), producing a real, visible hydration warning. Always use the existing pattern already established in `events/Show.vue`/`messages/Show.vue`: a `dateFnsLocales` map (`en`→`en-GB`, `nb`→`nb-NO`) plus `toLocaleString(dateFnsLocales[locale.value] ?? locale.value, { dateStyle: 'medium', timeStyle: 'short' })`.
