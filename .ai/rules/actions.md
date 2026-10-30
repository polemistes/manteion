---
paths:
  - 'resources/js/routes/**, resources/js/actions/**'
---

# Actions

## Regenerate Wayfinder with --with-form
vite.config.ts runs the Wayfinder plugin with formVariants: true, so the committed files carry `.form()` variants that pages use (`login.form()`, `store.form()`). After adding a route, run `php artisan wayfinder:generate --with-form`; a plain `wayfinder:generate` silently drops every `.form` and `npm run types:check` fails with TS2339 across a dozen pages.
